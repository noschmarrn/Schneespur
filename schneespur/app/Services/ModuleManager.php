<?php

namespace App\Services;

use App\Models\Setting;
use App\Services\Diagnostic\DiagnosticManager;
use App\Services\Extension\ModuleAssetRegistry;
use App\Services\Module\DependencyValidator;
use App\Services\ModuleLogger;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ModuleManager
{
    protected array $modules = [];

    protected array $disabledModules = [];

    protected bool $discovered = false;

    public function __construct(
        protected Application $app,
        protected string $modulesPath = '',
    ) {
        if ($this->modulesPath === '') {
            $this->modulesPath = base_path('modules');
        }
    }

    public function discover(): void
    {
        $this->modules = [];
        $this->discovered = true;

        if (! is_dir($this->modulesPath)) {
            return;
        }

        $dirs = glob($this->modulesPath . '/*/module.json');
        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $manifestPath) {
            $json = file_get_contents($manifestPath);
            if ($json === false) {
                continue;
            }

            $manifest = json_decode($json, true);
            if (! is_array($manifest) || empty($manifest['name'])) {
                Log::warning('ModuleManager: invalid module.json', ['path' => $manifestPath]);
                continue;
            }

            $slug = basename(dirname($manifestPath));
            $manifest['slug'] = $slug;
            $manifest['path'] = dirname($manifestPath);

            $this->modules[$slug] = $manifest;

            Log::debug('ModuleManager: module discovered', [
                'slug' => $slug,
                'version' => $manifest['version'] ?? 'unknown',
            ]);
        }
    }

    public function registerAutoloader(string $slug, string $namespace, string $path): void
    {
        $namespace = rtrim($namespace, '\\') . '\\';
        $path = rtrim($path, '/') . '/';

        spl_autoload_register(function (string $class) use ($namespace, $path) {
            if (! str_starts_with($class, $namespace)) {
                return;
            }

            $relative = substr($class, strlen($namespace));
            $file = $path . str_replace('\\', '/', $relative) . '.php';

            if (file_exists($file)) {
                require_once $file;
            }
        });
    }

    public function boot(): void
    {
        if (! $this->discovered) {
            $this->discover();
        }

        foreach ($this->modules as $slug => $manifest) {
            if (! $this->isEnabled($slug)) {
                continue;
            }

            // Reference example module — opt-in only via env var. Ensures the
            // bundled dev demo never auto-loads on customer installs even if the
            // old folder is still present after upgrading from older releases.
            if ($slug === 'example' && ! env('EXAMPLE_MODULE_ENABLED', false)) {
                continue;
            }

            $namespace = $manifest['namespace'] ?? null;
            $srcPath = ($manifest['path'] ?? '') . '/src';

            if ($namespace && is_dir($srcPath)) {
                $this->registerAutoloader($slug, $namespace, $srcPath);
            }

            $langPath = ($manifest['path'] ?? '') . '/lang';
            if (is_dir($langPath)) {
                $this->app->make('translator')->addNamespace($slug, $langPath);
                Log::debug('ModuleManager: translations registered', ['slug' => $slug]);
            }

            $providerClass = $manifest['service_provider'] ?? null;
            if (! $providerClass) {
                continue;
            }

            try {
                if (! class_exists($providerClass)) {
                    Log::error('ModuleManager: ServiceProvider class not found', [
                        'slug' => $slug,
                        'class' => $providerClass,
                    ]);
                    $this->autoDisable($slug, "ServiceProvider class not found: {$providerClass}");
                    continue;
                }

                $provider = new $providerClass($this->app);
                if (! $provider instanceof ServiceProvider) {
                    Log::error('ModuleManager: class is not a ServiceProvider', [
                        'slug' => $slug,
                        'class' => $providerClass,
                    ]);
                    $this->autoDisable($slug, "Class is not a ServiceProvider: {$providerClass}");
                    continue;
                }

                $provider->register();
                $provider->boot();

                $this->registerModuleAssets($slug, $manifest['path']);

                Log::debug('ModuleManager: module booted', [
                    'slug' => $slug,
                    'version' => $manifest['version'] ?? 'unknown',
                ]);
            } catch (\Throwable $e) {
                Log::error('ModuleManager: module boot failed', [
                    'slug' => $slug,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->autoDisable($slug, $e->getMessage());
                $this->reportDiagnostic('module_boot_failed', $slug, $e);
                $this->logEvent($slug, 'error', 'Boot failed', [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return true|string[]  True on success, array of error strings on failure
     */
    public function enable(string $slug): true|array
    {
        if (! isset($this->modules[$slug])) {
            return ['not_found'];
        }

        $validator = new DependencyValidator();
        $activeModules = $this->getActiveModuleManifests();
        $errors = $validator->validate($this->modules[$slug], $activeModules);

        if (! empty($errors)) {
            return $errors;
        }

        $this->disabledModules = array_diff($this->disabledModules, [$slug]);

        $this->createAssetSymlink($slug);

        return true;
    }

    /**
     * @return true|string[]  True on success, array of dependant slugs on failure
     */
    public function disable(string $slug): true|array
    {
        if (! isset($this->modules[$slug])) {
            return ['not_found'];
        }

        $validator = new DependencyValidator();
        $activeModules = $this->getActiveModuleManifests();
        $dependants = $validator->checkReverseDependencies($slug, $this->modules, $activeModules);

        if (! empty($dependants)) {
            return $dependants;
        }

        if (! in_array($slug, $this->disabledModules, true)) {
            $this->disabledModules[] = $slug;
        }

        $this->removeAssetSymlink($slug);

        Log::debug('ModuleManager: module disabled', ['slug' => $slug]);

        return true;
    }

    public function isEnabled(string $slug): bool
    {
        return isset($this->modules[$slug]) && ! in_array($slug, $this->disabledModules, true);
    }

    public function getManifest(string $slug): ?array
    {
        return $this->modules[$slug] ?? null;
    }

    public function getAll(): array
    {
        return $this->modules;
    }

    public function getDisabled(): array
    {
        return $this->disabledModules;
    }

    public function getPermissions(string $slug): array
    {
        $manifest = $this->getManifest($slug);

        return $manifest['requires_permissions'] ?? [];
    }

    public function registerSettings(string $slug, array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            $fullKey = $slug . '.' . $key;

            if (Setting::where('key', $fullKey)->exists()) {
                continue;
            }

            $type = match (true) {
                is_bool($value) => 'bool',
                is_int($value) => 'int',
                is_array($value) => 'json',
                default => 'string',
            };

            Setting::set($fullKey, $value, $type);
        }
    }

    public function cleanupSettings(string $slug): int
    {
        return Setting::where('key', 'like', $slug . '.%')->delete();
    }

    public function getActiveModuleManifests(): array
    {
        $active = [];
        foreach ($this->modules as $slug => $manifest) {
            if ($this->isEnabled($slug)) {
                $active[$slug] = $manifest;
            }
        }

        return $active;
    }

    protected function autoDisable(string $slug, string $reason): void
    {
        if (! in_array($slug, $this->disabledModules, true)) {
            $this->disabledModules[] = $slug;
        }

        Log::warning('ModuleManager: module auto-disabled', [
            'slug' => $slug,
            'reason' => $reason,
        ]);

        $this->logEvent($slug, 'warning', 'Auto-disabled', ['reason' => $reason]);
    }

    protected function registerModuleAssets(string $slug, string $modulePath): void
    {
        $manifestPath = rtrim($modulePath, '/') . '/dist/manifest.json';
        if (! file_exists($manifestPath)) {
            return;
        }

        $this->app->make(ModuleAssetRegistry::class)->registerAssets($slug, $modulePath);
        $this->createAssetSymlink($slug);
    }

    protected function createAssetSymlink(string $slug): void
    {
        $manifest = $this->modules[$slug] ?? null;
        if (! $manifest) {
            return;
        }

        $distPath = rtrim($manifest['path'], '/') . '/dist';
        if (! is_dir($distPath)) {
            return;
        }

        $publicModules = public_path('modules');
        if (! is_dir($publicModules)) {
            @mkdir($publicModules, 0755, true);
        }

        $link = $publicModules . '/' . $slug;
        if (! file_exists($link)) {
            @symlink($distPath, $link);
        }
    }

    protected function removeAssetSymlink(string $slug): void
    {
        $link = public_path('modules/' . $slug);
        if (is_link($link)) {
            @unlink($link);
        }
    }

    private function logEvent(string $slug, string $level, string $message, array $context = []): void
    {
        try {
            app(ModuleLogger::class)->log($slug, $level, $message, $context);
        } catch (\Throwable) {
        }
    }

    private function reportDiagnostic(string $type, string $slug, \Throwable $e): void
    {
        try {
            $manager = app(DiagnosticManager::class);
            $manager->report($type, [
                'module_slug' => $slug,
                'class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable) {
            // Never let diagnostic reporting interfere with module management
        }
    }
}
