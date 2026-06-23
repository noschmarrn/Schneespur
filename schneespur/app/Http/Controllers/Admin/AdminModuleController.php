<?php

namespace App\Http\Controllers\Admin;

use App\Events\Module\ModuleDisabled;
use App\Events\Module\ModuleEnabled;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\Module\DependencyValidator;
use App\Services\ModuleLogger;
use App\Services\Diagnostic\DiagnosticManager;
use App\Services\ModuleManager;
use App\Services\ModuleSignatureVerifier;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminModuleController extends Controller
{
    public function index(SchneespurModuleClient $client): View
    {
        Gate::authorize('settings.view');

        $installed = Module::all()->keyBy('slug');

        $catalogModules = [];
        $catalogError = null;

        try {
            $catalog = $client->fetchCatalog();
            $catalogModules = $catalog['modules'] ?? [];
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_catalog_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            Log::warning('schneespur-modules: catalog fetch failed in admin UI', [
                'error' => $e->getMessage(),
            ]);
            $catalogError = $e->getMessage();
        }

        $modules = [];
        foreach ($catalogModules as $catModule) {
            $slug = $catModule['slug'] ?? null;
            if (! $slug) {
                continue;
            }

            $local = $installed->get($slug);

            $modules[$slug] = [
                'slug' => $slug,
                'name' => SchneespurModuleClient::i18nPick($catModule['name'] ?? [], app()->getLocale()),
                'description' => SchneespurModuleClient::i18nPick($catModule['description'] ?? [], app()->getLocale()),
                'catalog_version' => $catModule['version'] ?? null,
                'category' => $catModule['category'] ?? null,
                'image' => $catModule['image'] ?? null,
                'installed' => $local !== null,
                'enabled' => $local?->enabled ?? false,
                'installed_version' => $local?->version,
                'has_update' => $local !== null && isset($catModule['version']) && version_compare($catModule['version'], $local->version, '>'),
                'is_orphan' => false,
                'download_url' => $catModule['download_url'] ?? null,
                'sha256' => $catModule['sha256'] ?? null,
                'size_bytes' => $catModule['size_bytes'] ?? null,
                'requires_permissions' => $catModule['requires_permissions'] ?? [],
                'info_url' => $this->safeHttpUrl($catModule['info_url'] ?? null),
                'trust_level' => $catModule['trust_level'] ?? null,
            ];
        }

        foreach ($installed as $slug => $local) {
            if (isset($modules[$slug])) {
                continue;
            }
            $modules[$slug] = [
                'slug' => $slug,
                'name' => $local->name ?? $slug,
                'description' => $local->description ?? '',
                'catalog_version' => null,
                'category' => $local->manifest_json['category'] ?? null,
                'image' => $local->manifest_json['image'] ?? null,
                'installed' => true,
                'enabled' => $local->enabled,
                'installed_version' => $local->version,
                'has_update' => false,
                'is_orphan' => true,
                'download_url' => null,
                'sha256' => null,
                'size_bytes' => null,
                'requires_permissions' => $this->resolveLocalPermissions($slug),
                'info_url' => null,
                'trust_level' => $local->trust_level,
            ];
        }

        return view('admin.settings.modules.index', [
            'modules' => $modules,
            'catalogError' => $catalogError,
        ]);
    }

    public function install(Request $request, string $slug, SchneespurModuleClient $client, SchneespurModuleInstaller $installer, ModuleSignatureVerifier $verifier): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $catalog = null;
        try {
            $catalog = $client->fetchCatalog();
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_download_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.catalog_fetch_failed', ['error' => $e->getMessage()]));
        }

        if ($catalog === null) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.catalog_unavailable'));
        }

        $moduleData = collect($catalog['modules'] ?? [])->firstWhere('slug', $slug);
        if (! $moduleData) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_found_in_catalog', ['slug' => $slug]));
        }

        try {
            $verifier->refreshTrust();
        } catch (\RuntimeException $e) {
            try {
                app(DiagnosticManager::class)->report('module_install_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.trust_refresh_failed', ['error' => $e->getMessage()]));
        }

        $sigResult = $verifier->verifyModuleManifest($moduleData);
        if (! $sigResult->isAllowed) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.signature_failed', ['slug' => $slug, 'reason' => $sigResult->message]));
        }

        try {
            $zipPath = $client->downloadModule(
                $slug,
                $moduleData['download_url'],
                $moduleData['sha256'],
                $moduleData['size_bytes'],
            );

            $success = $installer->install($zipPath, $slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_install_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.install_failed', ['slug' => $slug, 'error' => $e->getMessage()]));
        } finally {
            if (isset($zipPath)) {
                @unlink($zipPath);
            }
        }

        if (! $success) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.install_failed', ['slug' => $slug, 'error' => __('modules.directory_exists')]));
        }

        $module = Module::updateOrCreate(
            ['slug' => $slug],
            [
                'version' => $moduleData['version'] ?? '0.0.0',
                'enabled' => true,
                'manifest_json' => $moduleData,
                'signature_status' => $sigResult->status,
                'trust_level' => $moduleData['trust_level'] ?? null,
                'installed_at' => now(),
            ],
        );

        if ($sigResult->status === 'unsigned') {
            session()->flash('warning', __('modules.unsigned_warning', ['slug' => $slug]));
        }

        try {
            $this->runModuleMigrations($slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_migration_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            Log::error("Module migration failed during install of '{$slug}': {$e->getMessage()}");

            try {
                $this->rollbackModuleMigrations($slug);
            } catch (\Throwable) {
            }

            $installer->remove($slug);
            $module->delete();

            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.migration_failed', ['slug' => $slug, 'error' => $e->getMessage()]));
        }

        ModuleEnabled::dispatch($module);

        app(ModuleLogger::class)->info($slug, 'Module installed', [
            'version' => $moduleData['version'] ?? '0.0.0',
        ]);

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.installed', ['slug' => $slug]));
    }

    public function update(Request $request, string $slug, SchneespurModuleClient $client, SchneespurModuleInstaller $installer, ModuleSignatureVerifier $verifier): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $catalog = null;
        try {
            $catalog = $client->fetchCatalog();
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_catalog_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.catalog_fetch_failed', ['error' => $e->getMessage()]));
        }

        if ($catalog === null) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.catalog_unavailable'));
        }

        $moduleData = collect($catalog['modules'] ?? [])->firstWhere('slug', $slug);
        if (! $moduleData) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_found_in_catalog', ['slug' => $slug]));
        }

        try {
            $verifier->refreshTrust();
        } catch (\RuntimeException $e) {
            try {
                app(DiagnosticManager::class)->report('module_update_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.trust_refresh_failed', ['error' => $e->getMessage()]));
        }

        $sigResult = $verifier->verifyModuleManifest($moduleData);
        if (! $sigResult->isAllowed) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.signature_failed', ['slug' => $slug, 'reason' => $sigResult->message]));
        }

        try {
            $zipPath = $client->downloadModule(
                $slug,
                $moduleData['download_url'],
                $moduleData['sha256'],
                $moduleData['size_bytes'],
            );

            $success = $installer->update($zipPath, $slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_update_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.update_failed', ['slug' => $slug, 'error' => $e->getMessage()]));
        } finally {
            if (isset($zipPath)) {
                @unlink($zipPath);
            }
        }

        if (! $success) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.update_failed', ['slug' => $slug, 'error' => __('modules.extraction_failed')]));
        }

        Module::where('slug', $slug)->update([
            'version' => $moduleData['version'] ?? '0.0.0',
            'manifest_json' => $moduleData,
            'signature_status' => $sigResult->status,
            'trust_level' => $moduleData['trust_level'] ?? null,
        ]);

        if ($sigResult->status === 'unsigned') {
            session()->flash('warning', __('modules.unsigned_warning', ['slug' => $slug]));
        }

        try {
            $this->runModuleMigrations($slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_operation_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            Log::warning("Module migration failed during update of '{$slug}': {$e->getMessage()}");
        }

        app(ModuleLogger::class)->info($slug, 'Module updated', [
            'version' => $moduleData['version'] ?? '0.0.0',
        ]);

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.updated', ['slug' => $slug]));
    }

    public function enable(string $slug, ModuleManager $manager): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $module = Module::where('slug', $slug)->first();

        if (! $module) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_installed', ['slug' => $slug]));
        }

        $manager->discover();
        $manifest = $manager->getManifest($slug);

        if ($manifest) {
            $activeModules = $this->getDbActiveModuleManifests($manager);
            $validator = new DependencyValidator();
            $errors = $validator->validate($manifest, $activeModules);

            if (! empty($errors)) {
                return redirect()->route('admin.settings.modules.index')
                    ->with('error', $this->formatDependencyErrors($errors, $slug, $manager));
            }

            $cycle = $validator->detectCircularDependencies($slug, $manifest, $manager->getAll());

            if ($cycle !== null) {
                Log::warning('Module circular dependency detected', ['slug' => $slug, 'cycle' => $cycle]);

                return redirect()->route('admin.settings.modules.index')
                    ->with('error', __('modules.circular_dependency', [
                        'slug' => $slug,
                        'cycle' => implode(' → ', $cycle),
                    ]));
            }
        }

        $module->update(['enabled' => true]);

        try {
            $this->runModuleMigrations($slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_update_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            Log::error("Module migration failed for '{$slug}': {$e->getMessage()}");

            try {
                $this->rollbackModuleMigrations($slug);
            } catch (\Throwable) {
            }

            $module->update(['enabled' => false]);

            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.migration_failed', ['slug' => $slug, 'error' => $e->getMessage()]));
        }

        ModuleEnabled::dispatch($module);

        app(ModuleLogger::class)->info($slug, 'Module enabled');

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.enabled', ['slug' => $slug]));
    }

    public function disable(string $slug, ModuleManager $manager): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $module = Module::where('slug', $slug)->first();

        if (! $module) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_installed', ['slug' => $slug]));
        }

        $manager->discover();
        $activeModules = $this->getDbActiveModuleManifests($manager);
        $validator = new DependencyValidator();
        $dependants = $validator->checkReverseDependencies($slug, $manager->getAll(), $activeModules);

        if (! empty($dependants)) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.has_dependants', ['slug' => $slug, 'dependants' => implode(', ', $dependants)]));
        }

        $module->update(['enabled' => false]);

        ModuleDisabled::dispatch($module);

        app(ModuleLogger::class)->info($slug, 'Module disabled');

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.disabled', ['slug' => $slug]));
    }

    private function getDbActiveModuleManifests(ModuleManager $manager): array
    {
        $enabledSlugs = Module::where('enabled', true)->pluck('slug')->toArray();
        $active = [];

        foreach ($enabledSlugs as $slug) {
            $manifest = $manager->getManifest($slug);
            if ($manifest) {
                $active[$slug] = $manifest;
            }
        }

        return $active;
    }

    private function formatDependencyErrors(array $errors, string $slug, ModuleManager $manager): string
    {
        $messages = [];

        foreach ($errors as $error) {
            $parts = explode(':', $error);
            $type = $parts[0] ?? '';

            match ($type) {
                'requires_missing' => $messages[] = __('modules.dependency_missing', [
                    'slug' => $slug,
                    'dependency' => $parts[1] ?? '?',
                    'constraint' => $parts[2] ?? '*',
                ]),
                'requires_version' => $messages[] = __('modules.dependency_version', [
                    'slug' => $slug,
                    'dependency' => $parts[1] ?? '?',
                    'constraint' => $parts[2] ?? '*',
                    'actual' => $parts[3] ?? '?',
                ]),
                'conflict' => $messages[] = __('modules.dependency_conflict', [
                    'slug' => $slug,
                    'conflict' => $parts[1] ?? '?',
                ]),
                default => $messages[] = $error,
            };
        }

        return implode(' ', $messages);
    }

    private function moduleMigrationPath(string $slug): ?string
    {
        $path = base_path("modules/{$slug}/database/migrations");

        if (! File::isDirectory($path)) {
            return null;
        }

        if (empty(File::glob($path . '/*.php'))) {
            return null;
        }

        return "modules/{$slug}/database/migrations";
    }

    private function runModuleMigrations(string $slug): void
    {
        $path = $this->moduleMigrationPath($slug);

        if ($path === null) {
            return;
        }

        Artisan::call('migrate', [
            '--path' => $path,
            '--force' => true,
        ]);
    }

    private function rollbackModuleMigrations(string $slug): void
    {
        $path = $this->moduleMigrationPath($slug);

        if ($path === null) {
            return;
        }

        Artisan::call('migrate:rollback', [
            '--path' => $path,
            '--force' => true,
            '--step' => 999,
        ]);
    }

    /**
     * Only allow http(s) URLs to reach an href in the view. The catalog is an
     * external source, so a hostile entry could otherwise smuggle a
     * javascript:/data: scheme into the module info link (stored XSS).
     */
    private function safeHttpUrl(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    private function resolveLocalPermissions(string $slug): array
    {
        try {
            $manager = app(ModuleManager::class);
            return $manager->getPermissions($slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_settings_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            return [];
        }
    }

    public function remove(string $slug, SchneespurModuleInstaller $installer): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $module = Module::where('slug', $slug)->first();

        if (! $module) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_installed', ['slug' => $slug]));
        }

        $module->update(['enabled' => false]);

        ModuleDisabled::dispatch($module);

        try {
            $this->rollbackModuleMigrations($slug);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('module_settings_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'AdminModuleController',
                    'slug' => $slug,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }
            Log::warning("Module migration rollback failed for '{$slug}': {$e->getMessage()}");
        }

        $deleted = app(ModuleManager::class)->cleanupSettings($slug);
        if ($deleted > 0) {
            Log::info("Cleaned up {$deleted} settings for module '{$slug}'.");
        }

        $installer->remove($slug);

        $module->delete();

        app(ModuleLogger::class)->info($slug, 'Module removed');

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.removed', ['slug' => $slug]));
    }
}
