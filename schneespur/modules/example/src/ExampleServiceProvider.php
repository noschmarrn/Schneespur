<?php

namespace Schneespur\Module\Example;

use App\Events\JobCompleted;
use App\Services\Extension\DashboardWidgetRegistry;
use App\Services\Extension\FilterRegistry;
use App\Services\Extension\NavigationRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ExampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'example-module');

        $this->registerNavigation();
        $this->registerWidget();
        $this->registerFilters();
        $this->registerEventListeners();
        $this->registerRoutes();
    }

    protected function registerNavigation(): void
    {
        $nav = $this->app->make(NavigationRegistry::class);

        $nav->addItem(
            group: 'system',
            slug: 'example',
            label: 'Example',
            route: 'admin.example.settings',
            icon: 'heroicon-o-puzzle-piece',
            order: 200,
        );
    }

    protected function registerWidget(): void
    {
        $widgets = $this->app->make(DashboardWidgetRegistry::class);

        $widgets->registerWidget('example-card', [
            'label' => 'Example Module Active',
            'view' => 'example-module::widgets.example-card',
            'order' => 200,
            'size' => 'half',
        ]);
    }

    protected function registerFilters(): void
    {
        $filters = $this->app->make(FilterRegistry::class);

        $filters->register('schneespur.navigation.items', function (array $grouped): array {
            $grouped['modules'][] = [
                'group' => 'modules',
                'slug' => 'example-filter',
                'label' => 'Example Filter',
                'route' => 'admin.example.settings',
                'icon' => 'heroicon-o-funnel',
                'order' => 250,
                'permission' => null,
                'route_check' => null,
                'active_pattern' => 'admin.example.settings',
                'badge' => null,
            ];

            return $grouped;
        }, 150);

        $filters->register('schneespur.dashboard.kpis', function (array $widgets): array {
            $widgets[] = [
                'key' => 'example-filter-widget',
                'label' => 'Filter Demo',
                'view' => 'example-module::widgets.example-card',
                'order' => 250,
                'size' => 'half',
            ];

            return $widgets;
        }, 150);

        $filters->register('schneespur.job.notification.recipients', function (array $recipients, $job): array {
            Log::info('ExampleModule: notification recipients filter', [
                'job_id' => $job->id ?? null,
                'recipient_count' => count($recipients),
            ]);

            return $recipients;
        }, 150);
    }

    protected function registerEventListeners(): void
    {
        $this->app['events']->listen(JobCompleted::class, function (JobCompleted $event) {
            Log::info('ExampleModule: JobCompleted event received', [
                'job_id' => $event->job->id,
                'weather_available' => $event->weatherAvailable,
            ]);
        });
    }

    protected function registerRoutes(): void
    {
        Route::middleware(['web', 'auth'])
            ->prefix('admin/example')
            ->name('admin.example.')
            ->group(function () {
                Route::get('settings', [Http\Controllers\ExampleSettingsController::class, 'index'])
                    ->name('settings');
            });
    }
}
