<?php

namespace Schneespur\Module\Example;

use App\Enums\LifecyclePoint;
use App\Events\Customer\CustomerUpdated;
use App\Events\JobCompleted;
use App\Events\Module\ModuleEnabled;
use App\Events\Shift\WorkShiftStarted;
use App\Events\User\UserLoggedIn;
use App\Models\Setting;
use App\Services\Extension\DashboardWidgetRegistry;
use App\Services\Extension\DispatchStrategyRegistry;
use App\Services\Extension\FilterRegistry;
use App\Services\Extension\JobTypeRegistry;
use App\Services\Extension\LifecycleFieldRegistry;
use App\Services\Extension\ModuleApiRegistrar;
use App\Services\Extension\NavigationRegistry;
use App\Services\Extension\SlotRegistry;
use App\Services\ModuleManager;
use App\Services\Extension\TwoFactorMethodRegistry;
use App\Services\Notification\NotificationChannelRegistry;
use Schneespur\Module\Example\Dispatch\FirstAvailableStrategy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Schneespur\Module\Example\Auth\DummyTwoFactorMethod;
use Schneespur\Module\Example\Notification\DummyLogChannel;

class ExampleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->shouldBoot()) {
            return;
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'example-module');

        $this->registerSettings();
        $this->registerJobTypes();
        $this->registerLifecycleFields();
        $this->registerNavigation();
        $this->registerWidget();
        $this->registerFilters();
        $this->registerSlots();
        $this->registerEventListeners();
        $this->registerNotificationChannels();
        $this->registerTwoFactorMethods();
        $this->registerDispatchStrategy();
        $this->registerRoutes();
        $this->registerApiRoutes();
    }

    /**
     * Reference module — only loads when explicitly enabled.
     * Devs can enable for local exploration via .env: EXAMPLE_MODULE_ENABLED=true
     */
    protected function shouldBoot(): bool
    {
        return (bool) env('EXAMPLE_MODULE_ENABLED', false);
    }

    protected function registerSettings(): void
    {
        $this->app->make(ModuleManager::class)->registerSettings('example', [
            'greeting' => 'Hello from Example Module',
            'enabled_features' => 'all',
        ]);
    }

    protected function registerJobTypes(): void
    {
        $this->app->make(JobTypeRegistry::class)->registerType(
            'gruenpflege',
            'example::messages.job_type_gruenpflege',
            order: 500,
            module: 'example',
        );
    }

    protected function registerLifecycleFields(): void
    {
        $this->app->make(LifecycleFieldRegistry::class)->registerField(
            LifecyclePoint::JobEnd,
            'example.demo_field',
            [
                'view' => 'example-module::driver.fields.demo',
                'rules' => ['example_demo_field' => ['nullable', 'numeric', 'min:0']],
                'persist' => function ($job, array $validated): void {
                    // Demo only — a real module (e.g. inventory) writes its own table here.
                    Log::info('example module demo_field', [
                        'job_id' => $job->id,
                        'value' => $validated['example_demo_field'] ?? null,
                    ]);
                },
                'order' => 100,
            ],
        );
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

    protected function registerSlots(): void
    {
        $slots = $this->app->make(SlotRegistry::class);

        $slots->append('admin.content.after', 'example-module::slot-demo');
        $slots->append('driver.content.after', 'example-module::driver-slot-demo');
        $slots->append('portal.content.after', 'example-module::portal-slot-demo');
    }

    protected function registerEventListeners(): void
    {
        $this->app['events']->listen(JobCompleted::class, function (JobCompleted $event) {
            Log::info('ExampleModule: JobCompleted event received', [
                'job_id' => $event->job->id,
                'weather_available' => $event->weatherAvailable,
            ]);
        });

        $this->app['events']->listen(WorkShiftStarted::class, function (WorkShiftStarted $event) {
            Log::info('ExampleModule: WorkShiftStarted event received', [
                'shift_id' => $event->workShift->id,
                'user_id' => $event->user->id,
            ]);
        });

        $this->app['events']->listen(CustomerUpdated::class, function (CustomerUpdated $event) {
            Log::info('ExampleModule: CustomerUpdated event received', [
                'customer_id' => $event->customer->id,
                'customer_name' => $event->customer->name,
            ]);
        });

        $this->app['events']->listen(UserLoggedIn::class, function (UserLoggedIn $event) {
            Log::info('ExampleModule: UserLoggedIn event received', [
                'user_id' => $event->user->id,
                'user_email' => $event->user->email,
            ]);
        });

        $this->app['events']->listen(ModuleEnabled::class, function (ModuleEnabled $event) {
            Log::info('ExampleModule: ModuleEnabled event received', [
                'module_slug' => $event->module->slug,
            ]);
        });
    }

    protected function registerNotificationChannels(): void
    {
        $registry = $this->app->make(NotificationChannelRegistry::class);
        $registry->register('dummy-log', DummyLogChannel::class);
    }

    protected function registerTwoFactorMethods(): void
    {
        $registry = $this->app->make(TwoFactorMethodRegistry::class);
        $registry->registerMethod('dummy-2fa', DummyTwoFactorMethod::class);
    }

    protected function registerDispatchStrategy(): void
    {
        $registry = $this->app->make(DispatchStrategyRegistry::class);
        $registry->register('first_available', FirstAvailableStrategy::class);
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

    protected function registerApiRoutes(): void
    {
        $this->app->make(ModuleApiRegistrar::class)->routes('example', 1, function () {
            Route::get('status', [Http\Controllers\ExampleApiController::class, 'status'])
                ->name('status');
        });
    }
}
