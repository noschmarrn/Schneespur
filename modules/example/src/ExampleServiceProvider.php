<?php

namespace Schneespur\Module\Example;

use App\Events\JobCompleted;
use App\Services\Extension\DashboardWidgetRegistry;
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
