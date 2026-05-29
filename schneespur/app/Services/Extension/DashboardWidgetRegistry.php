<?php

namespace App\Services\Extension;

use App\Models\User;
use App\Services\Diagnostic\DiagnosticManager;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class DashboardWidgetRegistry extends ExtensionRegistry
{
    public function registerWidget(string $slug, array $config): void
    {
        $config = array_merge([
            'slug' => $slug,
            'label' => $slug,
            'view' => null,
            'dataCallback' => null,
            'order' => 100,
            'permission' => null,
            'condition' => null,
            'size' => 'full',
        ], $config, ['slug' => $slug]);

        $this->register($slug, $config);
    }

    /**
     * @return array<int, array{slug: string, label: string, view: string|null, data: mixed, order: int, permission: string|null, size: string, error: bool}>
     */
    public function getWidgets(?User $user = null): array
    {
        $widgets = [];

        foreach ($this->items as $config) {
            if ($config['permission'] !== null && $user !== null && ! Gate::forUser($user)->allows($config['permission'])) {
                continue;
            }

            if ($config['condition'] !== null) {
                try {
                    if (! ($config['condition'])()) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    try {
                        app(DiagnosticManager::class)->report('widget_render_failed', [
                            'error' => $e->getMessage(),
                            'exception_class' => get_class($e),
                        ], [
                            'source' => 'DashboardWidgetRegistry',
                            'slug' => $config['slug'],
                        ]);
                    } catch (\Throwable) {
                        // Never let diagnostic reporting break the original flow
                    }

                    Log::warning("DashboardWidgetRegistry: condition callback failed for '{$config['slug']}': {$e->getMessage()}");
                    continue;
                }
            }

            $data = null;
            $error = false;

            if ($config['dataCallback'] !== null) {
                try {
                    $data = ($config['dataCallback'])();
                } catch (\Throwable $e) {
                    try {
                        app(DiagnosticManager::class)->report('widget_render_failed', [
                            'error' => $e->getMessage(),
                            'exception_class' => get_class($e),
                        ], [
                            'source' => 'DashboardWidgetRegistry',
                            'slug' => $config['slug'],
                        ]);
                    } catch (\Throwable) {
                        // Never let diagnostic reporting break the original flow
                    }

                    Log::warning("DashboardWidgetRegistry: data callback exception for '{$config['slug']}': {$e->getMessage()}");
                    $error = true;
                }
            }

            $widgets[] = [
                'slug' => $config['slug'],
                'label' => $config['label'],
                'view' => $config['view'],
                'data' => $data,
                'order' => $config['order'],
                'permission' => $config['permission'],
                'size' => $config['size'],
                'error' => $error,
            ];
        }

        usort($widgets, fn (array $a, array $b) => $a['order'] <=> $b['order']);

        return $widgets;
    }
}
