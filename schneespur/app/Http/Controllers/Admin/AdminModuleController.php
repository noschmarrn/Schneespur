<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Services\SchneespurModuleClient;
use App\Services\SchneespurModuleInstaller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AdminModuleController extends Controller
{
    public function index(SchneespurModuleClient $client): View
    {
        $installed = Module::all()->keyBy('slug');

        $catalogModules = [];
        $catalogError = null;

        try {
            $catalog = $client->fetchCatalog();
            if ($catalog !== null) {
                $catalogModules = $catalog['modules'] ?? [];
            } else {
                $state = $client->loadState();
                $catalogModules = $state['installed'] ?? [];
            }
        } catch (\Throwable $e) {
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
            ];
        }

        return view('admin.settings.modules.index', [
            'modules' => $modules,
            'catalogError' => $catalogError,
        ]);
    }

    public function install(Request $request, string $slug, SchneespurModuleClient $client, SchneespurModuleInstaller $installer): RedirectResponse
    {
        $catalog = null;
        try {
            $catalog = $client->fetchCatalog();
        } catch (\Throwable $e) {
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
            $zipPath = $client->downloadModule(
                $slug,
                $moduleData['download_url'],
                $moduleData['sha256'],
                $moduleData['size_bytes'],
            );

            $success = $installer->install($zipPath, $slug);
        } catch (\Throwable $e) {
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

        Module::updateOrCreate(
            ['slug' => $slug],
            [
                'version' => $moduleData['version'] ?? '0.0.0',
                'enabled' => true,
                'manifest_json' => $moduleData,
                'installed_at' => now(),
            ],
        );

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.installed', ['slug' => $slug]));
    }

    public function update(Request $request, string $slug, SchneespurModuleClient $client, SchneespurModuleInstaller $installer): RedirectResponse
    {
        $catalog = null;
        try {
            $catalog = $client->fetchCatalog();
        } catch (\Throwable $e) {
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
            $zipPath = $client->downloadModule(
                $slug,
                $moduleData['download_url'],
                $moduleData['sha256'],
                $moduleData['size_bytes'],
            );

            $success = $installer->update($zipPath, $slug);
        } catch (\Throwable $e) {
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
        ]);

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.updated', ['slug' => $slug]));
    }

    public function enable(string $slug): RedirectResponse
    {
        $module = Module::where('slug', $slug)->first();

        if (! $module) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_installed', ['slug' => $slug]));
        }

        $module->update(['enabled' => true]);

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.enabled', ['slug' => $slug]));
    }

    public function disable(string $slug): RedirectResponse
    {
        $module = Module::where('slug', $slug)->first();

        if (! $module) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_installed', ['slug' => $slug]));
        }

        $module->update(['enabled' => false]);

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.disabled', ['slug' => $slug]));
    }

    public function remove(string $slug, SchneespurModuleInstaller $installer): RedirectResponse
    {
        $module = Module::where('slug', $slug)->first();

        if (! $module) {
            return redirect()->route('admin.settings.modules.index')
                ->with('error', __('modules.not_installed', ['slug' => $slug]));
        }

        $module->update(['enabled' => false]);

        $installer->remove($slug);

        $module->delete();

        return redirect()->route('admin.settings.modules.index')
            ->with('success', __('modules.removed', ['slug' => $slug]));
    }
}
