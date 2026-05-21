<?php

namespace App\Services;

use App\Services\Diagnostic\DiagnosticManager;
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
            }
        }
    }

    public function enable(string $slug): bool
    {
        if (! isset($this->modules[$slug])) {
            return false;
        }

        $this->disabledModules = array_diff($this->disabledModules, [$slug]);

        return true;
    }

    public function disable(string $slug): bool
    {
        if (! isset($this->modules[$slug])) {
            return false;
        }

        if (! in_array($slug, $this->disabledModules, true)) {
            $this->disabledModules[] = $slug;
        }

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

    protected function autoDisable(string $slug, string $reason): void
    {
        if (! in_array($slug, $this->disabledModules, true)) {
            $this->disabledModules[] = $slug;
        }

        Log::warning('ModuleManager: module auto-disabled', [
            'slug' => $slug,
            'reason' => $reason,
        ]);
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
