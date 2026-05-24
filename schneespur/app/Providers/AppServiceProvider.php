<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\CustomerObject;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\JobPolicy;
use App\Services\AlertService;
use App\Services\Diagnostic\DiagnosticManager;
use App\Services\Diagnostic\DiagnosticPayloadSanitizer;
use App\Services\Diagnostic\DiagnosticReporterRegistry;
use App\Services\Extension\DashboardWidgetRegistry;
use App\Services\Extension\FilterRegistry;
use App\Services\Extension\NavigationRegistry;
use App\Services\Extension\PermissionRegistry;
use App\Services\Extension\RoleTemplateRegistry;
use App\Services\Extension\SlotRegistry;
use App\Services\Extension\TwoFactorMethodRegistry;
use App\Services\Dispatch\ManualDispatchStrategy;
use App\Services\Extension\DispatchStrategyRegistry;
use App\Services\Notification\EmailNotificationChannel;
use App\Services\Notification\NotificationChannelRegistry;
use App\Services\ForecastService;
use App\Services\ModuleManager;
use App\Services\RetentionService;
use App\Services\SchneespurUpdater;
use App\Services\SeasonService;
use App\Services\Translation\BrandedTranslator;
use App\Services\Backup\BackupTargetRegistry;
use App\Services\Backup\LocalBackupTarget;
use App\Services\Pdf\DomPdfRenderer;
use App\Services\Pdf\PdfRendererRegistry;
use App\Services\Scheduler\ScheduledTaskRegistry;
use App\Services\Scheduler\Tasks\CronHeartbeatTask;
use App\Services\Scheduler\Tasks\QueueWorkTask;
use App\Services\Scheduler\Tasks\RetentionDeleteTask;
use App\Services\Scheduler\Tasks\UpdateCheckTask;
use App\Services\Storage\LocalStorageBackend;
use App\Services\Storage\StorageBackendRegistry;
use App\Services\Weather\BrightSkyProvider;
use App\Services\Weather\MetNorwayProvider;
use App\Services\Weather\OpenMeteoApiProvider;
use App\Services\Weather\OpenMeteoFreeProvider;
use App\Services\Weather\WeatherProviderRegistry;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AlertService::class);
        $this->app->singleton(DashboardWidgetRegistry::class);
        $this->app->singleton(FilterRegistry::class);
        $this->app->singleton(NavigationRegistry::class);
        $this->app->singleton(PermissionRegistry::class);
        $this->app->singleton(RoleTemplateRegistry::class);
        $this->app->singleton(SlotRegistry::class);
        $this->app->singleton(TwoFactorMethodRegistry::class);
        $this->app->singleton(DiagnosticPayloadSanitizer::class);
        $this->app->singleton(DiagnosticReporterRegistry::class, fn ($app) => new DiagnosticReporterRegistry($app));
        $this->app->singleton(DiagnosticManager::class);
        $this->app->singleton(ModuleManager::class, fn ($app) => new ModuleManager($app));
        $this->app->singleton(NotificationChannelRegistry::class, function ($app) {
            $registry = new NotificationChannelRegistry($app, $app->make(FilterRegistry::class));
            $registry->register('email', EmailNotificationChannel::class);

            return $registry;
        });

        $this->app->singleton(DispatchStrategyRegistry::class, function ($app) {
            $registry = new DispatchStrategyRegistry($app);
            $registry->register('manual', ManualDispatchStrategy::class);

            return $registry;
        });

        $this->app->singleton(StorageBackendRegistry::class, function ($app) {
            $registry = new StorageBackendRegistry($app);
            $registry->register('local', LocalStorageBackend::class);

            return $registry;
        });

        $this->app->singleton(BackupTargetRegistry::class, function ($app) {
            $registry = new BackupTargetRegistry($app);
            $registry->register('local', LocalBackupTarget::class);

            return $registry;
        });

        $this->app->singleton(ScheduledTaskRegistry::class, function ($app) {
            $registry = new ScheduledTaskRegistry($app);
            $registry->register('retention-delete', RetentionDeleteTask::class);
            $registry->register('update-check', UpdateCheckTask::class);
            $registry->register('queue-work', QueueWorkTask::class);
            $registry->register('cron-heartbeat', CronHeartbeatTask::class);

            return $registry;
        });

        $this->app->singleton(PdfRendererRegistry::class, function ($app) {
            $registry = new PdfRendererRegistry($app);
            $registry->register('dompdf', DomPdfRenderer::class);

            return $registry;
        });

        $this->app->singleton(WeatherProviderRegistry::class, function ($app) {
            $registry = new WeatherProviderRegistry($app);
            $registry->register('openmeteo_free', OpenMeteoFreeProvider::class);
            $registry->register('openmeteo_api', OpenMeteoApiProvider::class);
            $registry->register('brightsky', BrightSkyProvider::class);
            $registry->register('met_norway', MetNorwayProvider::class);

            return $registry;
        });

        if (empty(config('app.key'))) {
            config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        }

        $this->app->extend('translator', function ($translator, $app) {
            if ($translator instanceof BrandedTranslator) {
                return $translator;
            }
            $branded = new BrandedTranslator($translator->getLoader(), $translator->getLocale());
            if ($fallback = $translator->getFallback()) {
                $branded->setFallback($fallback);
            }
            return $branded;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at('*');
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
        );

        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $locale = Setting::get('default_locale');
        if ($locale && in_array($locale, ['de', 'en'], true)) {
            App::setLocale($locale);
        }

        Gate::policy(Job::class, JobPolicy::class);

        Gate::before(function (User $user, string $ability) {
            if ($user->isAdmin()) {
                return true;
            }
        });

        Route::bind('driver', fn (string $value) => User::drivers()->findOrFail($value));
        Route::model('serviceJob', Job::class);
        Route::model('object', CustomerObject::class);

        View::composer('layouts.admin', function ($view) {
            try {
                $view->with('alertCount', app(AlertService::class)->openCount());
            } catch (\Exception) {
                $view->with('alertCount', 0);
            }
        });

        Blade::directive('extensionSlot', fn ($expression) => "<?php echo app(\\App\\Services\\Extension\\SlotRegistry::class)->render({$expression}, auth()->user()); ?>");

        $this->registerCorePermissions();
        $this->registerCoreNavigation();
        $this->registerCoreDashboardWidgets();

        $this->app->booted(function () {
            app(ModuleManager::class)->boot();
            $this->registerPermissionGates();
        });
    }

    private function registerCorePermissions(): void
    {
        $registry = app(PermissionRegistry::class);

        $permissions = [
            ['dashboard.view', __('permission.dashboard_view'), 'dashboard'],
            ['customers.view', __('permission.customers_view'), 'customers'],
            ['customers.edit', __('permission.customers_edit'), 'customers'],
            ['customers.delete', __('permission.customers_delete'), 'customers'],
            ['drivers.view', __('permission.drivers_view'), 'drivers'],
            ['drivers.edit', __('permission.drivers_edit'), 'drivers'],
            ['drivers.delete', __('permission.drivers_delete'), 'drivers'],
            ['vehicles.view', __('permission.vehicles_view'), 'vehicles'],
            ['vehicles.edit', __('permission.vehicles_edit'), 'vehicles'],
            ['vehicles.delete', __('permission.vehicles_delete'), 'vehicles'],
            ['jobs.view', __('permission.jobs_view'), 'jobs'],
            ['jobs.edit', __('permission.jobs_edit'), 'jobs'],
            ['jobs.delete', __('permission.jobs_delete'), 'jobs'],
            ['workshifts.view', __('permission.workshifts_view'), 'workshifts'],
            ['reports.view', __('permission.reports_view'), 'reports'],
            ['alerts.view', __('permission.alerts_view'), 'alerts'],
            ['alerts.resolve', __('permission.alerts_resolve'), 'alerts'],
            ['settings.view', __('permission.settings_view'), 'settings'],
            ['settings.edit', __('permission.settings_edit'), 'settings'],
            ['dsgvo.view', __('permission.dsgvo_view'), 'dsgvo'],
            ['dsgvo.edit', __('permission.dsgvo_edit'), 'dsgvo'],
            ['gps.view', __('permission.gps_view'), 'gps'],
            ['help.view', __('permission.help_view'), 'help'],
            ['users.view', __('permission.users_view'), 'users'],
            ['users.edit', __('permission.users_edit'), 'users'],
            ['users.delete', __('permission.users_delete'), 'users'],
            ['crontasks.view', __('permission.crontasks_view'), 'crontasks'],
            ['crontasks.manage', __('permission.crontasks_manage'), 'crontasks'],
        ];

        foreach ($permissions as [$slug, $label, $group]) {
            $registry->registerPermission($slug, $label, $group);
        }
    }

    private function registerCoreNavigation(): void
    {
        $nav = app(NavigationRegistry::class);

        $nav->addGroup('top', '', 0);
        $nav->addGroup('stammdaten', __('admin.nav_group_master_data'), 10);
        $nav->addGroup('einsaetze', __('admin.nav_group_operations'), 20);
        $nav->addGroup('auswertungen', __('admin.nav_group_reports'), 30);
        $nav->addGroup('system', __('admin.nav_group_system'), 40);

        // Top-level
        $nav->addItem(
            group: 'top',
            slug: 'dashboard',
            label: __('admin.nav_dashboard'),
            route: 'admin.dashboard',
            icon: 'M2.25 12l8.954-8.955a1.126 1.126 0 011.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25',
            order: 10,
            permission: 'dashboard.view',
            activePattern: 'admin.dashboard',
        );

        // Stammdaten
        $nav->addItem(
            group: 'stammdaten',
            slug: 'customers',
            label: __('admin.nav_customers'),
            route: 'admin.customers.index',
            icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
            order: 10,
            permission: 'customers.view',
            activePattern: 'admin.customers.*',
        );
        $nav->addItem(
            group: 'stammdaten',
            slug: 'drivers',
            label: __('admin.nav_drivers'),
            route: 'admin.drivers.index',
            icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
            order: 20,
            permission: 'drivers.view',
            activePattern: 'admin.drivers.*&!admin.drivers.archived',
        );
        $nav->addItem(
            group: 'stammdaten',
            slug: 'archived-drivers',
            label: __('admin.nav_archived_drivers'),
            route: 'admin.drivers.archived',
            icon: 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z',
            order: 30,
            permission: 'drivers.view',
            activePattern: 'admin.drivers.archived',
        );
        $nav->addItem(
            group: 'stammdaten',
            slug: 'vehicles',
            label: __('admin.nav_vehicles'),
            route: 'admin.vehicles.index',
            icon: 'M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0H6.375c-.621 0-1.125-.504-1.125-1.125V14.25m17.25 0V6.375c0-.621-.504-1.125-1.125-1.125H4.125c-.621 0-1.125.504-1.125 1.125v7.875m18 0h-1.5m-17.25 0h1.5',
            order: 40,
            permission: 'vehicles.view',
            activePattern: 'admin.vehicles.*',
        );

        // Einsätze
        $nav->addItem(
            group: 'einsaetze',
            slug: 'jobs',
            label: __('admin.nav_jobs'),
            route: 'admin.jobs.index',
            icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z',
            order: 10,
            permission: 'jobs.view',
            routeCheck: 'admin.jobs.index',
            activePattern: 'admin.jobs.*',
        );
        $nav->addItem(
            group: 'einsaetze',
            slug: 'workshifts',
            label: __('admin.nav_workshifts'),
            route: 'admin.workshifts.index',
            icon: 'M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z',
            order: 20,
            permission: 'workshifts.view',
            routeCheck: 'admin.workshifts.index',
            activePattern: 'admin.workshifts.*',
        );

        // Auswertungen
        $nav->addItem(
            group: 'auswertungen',
            slug: 'overview-daily',
            label: __('admin.nav_overview_daily'),
            route: 'admin.overview.daily',
            icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z',
            order: 10,
            permission: 'reports.view',
            routeCheck: 'admin.overview.daily',
            activePattern: 'admin.overview.daily',
        );
        $nav->addItem(
            group: 'auswertungen',
            slug: 'overview-monthly',
            label: __('admin.nav_overview_monthly'),
            route: 'admin.overview.monthly',
            icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5||M3 11.25h18',
            order: 20,
            permission: 'reports.view',
            routeCheck: 'admin.overview.monthly',
            activePattern: 'admin.overview.monthly',
        );
        $nav->addItem(
            group: 'auswertungen',
            slug: 'overview-driver-report',
            label: __('admin.nav_overview_driver_report'),
            route: 'admin.overview.driver-report',
            icon: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
            order: 30,
            permission: 'reports.view',
            routeCheck: 'admin.overview.driver-report',
            activePattern: 'admin.overview.driver-report',
        );
        $nav->addItem(
            group: 'auswertungen',
            slug: 'overview-customer-report',
            label: __('admin.nav_overview_customer_report'),
            route: 'admin.overview.customer-report',
            icon: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z',
            order: 40,
            permission: 'reports.view',
            routeCheck: 'admin.overview.customer-report',
            activePattern: 'admin.overview.customer-report',
        );
        $nav->addItem(
            group: 'auswertungen',
            slug: 'exports-csv',
            label: __('admin.nav_exports'),
            route: 'admin.exports.csv',
            icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
            order: 50,
            permission: 'reports.view',
            routeCheck: 'admin.exports.csv',
            activePattern: 'admin.exports.*',
        );

        // System
        $nav->addItem(
            group: 'system',
            slug: 'users',
            label: __('admin.nav_users'),
            route: 'admin.users.index',
            icon: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
            order: 5,
            permission: 'users.view',
            activePattern: 'admin.users.*',
        );
        $nav->addItem(
            group: 'system',
            slug: 'alerts',
            label: __('admin.nav_alerts'),
            route: 'admin.alerts.index',
            icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z',
            order: 10,
            permission: 'alerts.view',
            routeCheck: 'admin.alerts.index',
            activePattern: 'admin.alerts.*',
            badge: 'alertCount',
        );
        $nav->addItem(
            group: 'system',
            slug: 'gps-status',
            label: __('admin.nav_gps_status'),
            route: 'admin.owntracks.overview',
            icon: 'M15 10.5a3 3 0 11-6 0 3 3 0 016 0z||M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z',
            order: 20,
            permission: 'gps.view',
            routeCheck: 'admin.owntracks.overview',
            activePattern: 'admin.owntracks.*',
        );
        $nav->addItem(
            group: 'system',
            slug: 'dsgvo',
            label: __('admin.nav_dsgvo'),
            route: 'admin.dsgvo.index',
            icon: 'M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z',
            order: 30,
            permission: 'dsgvo.view',
            activePattern: 'admin.dsgvo.*',
        );
        $nav->addItem(
            group: 'system',
            slug: 'help',
            label: __('admin.nav_help'),
            route: 'admin.help.index',
            icon: 'M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M12 18.75h.007v.008H12v-.008z||M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
            order: 40,
            permission: 'help.view',
            routeCheck: 'admin.help.index',
            activePattern: 'admin.help.*',
        );
        $nav->addItem(
            group: 'system',
            slug: 'crontasks',
            label: __('admin.nav_crontasks'),
            route: 'admin.crontasks.index',
            icon: 'M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z',
            order: 45,
            permission: 'crontasks.view',
            activePattern: 'admin.crontasks.*',
        );
        $nav->addItem(
            group: 'system',
            slug: 'settings',
            label: __('admin.nav_settings'),
            route: 'admin.settings.index',
            icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z||M15 12a3 3 0 11-6 0 3 3 0 016 0z',
            order: 50,
            permission: 'settings.view',
            activePattern: 'admin.settings.*',
        );
        $nav->addItem(
            group: 'system',
            slug: 'modules',
            label: __('admin.nav_modules'),
            route: 'admin.settings.modules.index',
            icon: 'M21 7.5l-9-5.25L3 7.5m18 0l-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9',
            order: 51,
            permission: 'settings.view',
            activePattern: 'admin.settings.modules.*',
        );
    }

    private function registerPermissionGates(): void
    {
        $registry = app(PermissionRegistry::class);

        foreach ($registry->all() as $slug => $entry) {
            Gate::define($slug, fn (User $user) => $user->hasPermission($slug));
        }
    }

    private function registerCoreDashboardWidgets(): void
    {
        $registry = app(DashboardWidgetRegistry::class);

        $registry->registerWidget('onboarding-checklist', [
            'label' => 'Onboarding Checklist',
            'view' => 'admin.dashboard.widgets.onboarding-checklist',
            'order' => 10,
            'size' => 'full',
            'condition' => function () {
                if (Setting::get('onboarding_dismissed') === '1') {
                    return false;
                }
                $customerCount = Customer::count();
                $driverCount = User::drivers()->count();
                $vehicleCount = Vehicle::count();
                $cronLastRun = cache()->get('cron.last_run');
                $cronOk = $cronLastRun && $cronLastRun->diffInMinutes(now()) < 5;
                $mailConfigured = ! empty(config('mail.mailers.smtp.host'));
                $onboarding = [
                    'company'   => ! empty(Setting::get('company_name')),
                    'driver'    => $driverCount > 0,
                    'customer'  => $customerCount > 0,
                    'vehicle'   => $vehicleCount > 0,
                    'email'     => $mailConfigured,
                    'cron'      => $cronOk,
                ];
                $onboardingComplete = ! in_array(false, $onboarding, true);
                if ($onboardingComplete) {
                    Setting::set('onboarding_dismissed', '1');
                    return false;
                }
                return true;
            },
            'dataCallback' => function () {
                $customerCount = Customer::count();
                $driverCount = User::drivers()->count();
                $vehicleCount = Vehicle::count();
                $cronLastRun = cache()->get('cron.last_run');
                $cronOk = $cronLastRun && $cronLastRun->diffInMinutes(now()) < 5;
                $mailConfigured = ! empty(config('mail.mailers.smtp.host'));
                return [
                    'onboarding' => [
                        'company'   => ! empty(Setting::get('company_name')),
                        'driver'    => $driverCount > 0,
                        'customer'  => $customerCount > 0,
                        'vehicle'   => $vehicleCount > 0,
                        'email'     => $mailConfigured,
                        'cron'      => $cronOk,
                    ],
                ];
            },
        ]);

        $registry->registerWidget('cron-warning', [
            'label' => 'Cron Warning',
            'view' => 'admin.dashboard.widgets.cron-warning',
            'order' => 20,
            'size' => 'full',
            'condition' => function () {
                $cronLastRun = cache()->get('cron.last_run');
                return ! ($cronLastRun && $cronLastRun->diffInMinutes(now()) < 5);
            },
            'dataCallback' => null,
        ]);

        $registry->registerWidget('stat-cards', [
            'label' => 'Statistics',
            'view' => 'admin.dashboard.widgets.stat-cards',
            'order' => 30,
            'size' => 'full',
            'dataCallback' => function () {
                return [
                    'customerCount' => Customer::count(),
                    'driverCount' => User::drivers()->count(),
                    'vehicleCount' => Vehicle::count(),
                ];
            },
        ]);

        $registry->registerWidget('retention-hint', [
            'label' => 'Retention Hint',
            'view' => 'admin.dashboard.widgets.retention-hint',
            'order' => 40,
            'size' => 'full',
            'condition' => function () {
                return app(RetentionService::class)->getRetentionStats() !== null;
            },
            'dataCallback' => function () {
                return [
                    'retentionStats' => app(RetentionService::class)->getRetentionStats(),
                ];
            },
        ]);

        $registry->registerWidget('weather-forecast', [
            'label' => 'Weather',
            'view' => 'admin.dashboard.widgets.weather-forecast',
            'order' => 50,
            'size' => 'full',
            'dataCallback' => function () {
                $companyLat = Setting::get('company_lat');
                $companyLon = Setting::get('company_lon');
                $weather = null;
                $weatherMissing = false;

                if ($companyLat && $companyLon) {
                    $weather = app(ForecastService::class)->current((float) $companyLat, (float) $companyLon);
                } else {
                    $weatherMissing = true;
                }

                return [
                    'weather' => $weather,
                    'weatherMissing' => $weatherMissing,
                ];
            },
        ]);

        $registry->registerWidget('season-statistics', [
            'label' => 'Season Statistics',
            'view' => 'admin.dashboard.widgets.season-statistics',
            'order' => 60,
            'size' => 'full',
            'dataCallback' => function () {
                $season = app(SeasonService::class)->currentOrLastSeason();
                $isSqlite = DB::getDriverName() === 'sqlite';
                $monthExpr = $isSqlite
                    ? "CAST(strftime('%m', started_at) AS INTEGER)"
                    : 'MONTH(started_at)';

                $jobsPerMonth = Job::selectRaw("{$monthExpr} as month, COUNT(*) as job_count")
                    ->whereBetween('started_at', [$season->start, $season->end])
                    ->groupByRaw($monthExpr)
                    ->pluck('job_count', 'month');

                $durationExpr = $isSqlite
                    ? "SUM((JULIANDAY(COALESCE(ended_at, started_at)) - JULIANDAY(started_at)) * 1440)"
                    : "SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, started_at)))";

                $seasonTotalMinutes = (int) Job::selectRaw("{$durationExpr} as total_minutes")
                    ->whereBetween('started_at', [$season->start, $season->end])
                    ->whereNotNull('ended_at')
                    ->value('total_minutes');

                $seasonJobCount = Job::whereBetween('started_at', [$season->start, $season->end])->count();

                return [
                    'season' => $season,
                    'jobsPerMonth' => $jobsPerMonth,
                    'seasonTotalMinutes' => $seasonTotalMinutes,
                    'seasonJobCount' => $seasonJobCount,
                ];
            },
        ]);

        $registry->registerWidget('driver-ranking', [
            'label' => 'Driver Ranking',
            'view' => 'admin.dashboard.widgets.driver-ranking',
            'order' => 70,
            'size' => 'full',
            'dataCallback' => function () {
                $season = app(SeasonService::class)->currentOrLastSeason();
                $isSqlite = DB::getDriverName() === 'sqlite';
                $durationExpr = $isSqlite
                    ? "SUM((JULIANDAY(COALESCE(ended_at, started_at)) - JULIANDAY(started_at)) * 1440)"
                    : "SUM(TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, started_at)))";

                $drivers = User::withAnonymized()
                    ->drivers()
                    ->withCount(['serviceJobs as season_jobs' => fn ($q) => $q->whereBetween('started_at', [$season->start, $season->end])])
                    ->get();

                $driverMinutes = Job::selectRaw("user_id, {$durationExpr} as total_minutes")
                    ->whereBetween('started_at', [$season->start, $season->end])
                    ->whereNotNull('ended_at')
                    ->groupBy('user_id')
                    ->pluck('total_minutes', 'user_id');

                $driverRanking = $drivers->map(function ($driver) use ($driverMinutes) {
                    return (object) [
                        'driver' => $driver,
                        'season_jobs' => $driver->season_jobs,
                        'total_minutes' => (int) ($driverMinutes[$driver->id] ?? 0),
                    ];
                })->sortByDesc('season_jobs')->values();

                return ['driverRanking' => $driverRanking];
            },
        ]);

        $registry->registerWidget('recent-jobs', [
            'label' => 'Recent Jobs',
            'view' => 'admin.dashboard.widgets.recent-jobs',
            'order' => 80,
            'size' => 'full',
            'dataCallback' => function () {
                return [
                    'recentJobs' => Job::with(['customer', 'customerObject.customer', 'user' => fn ($q) => $q->withAnonymized()])
                        ->whereNotNull('ended_at')
                        ->latest('started_at')
                        ->take(10)
                        ->get(),
                ];
            },
        ]);

        $registry->registerWidget('system-status', [
            'label' => 'System Status',
            'view' => 'admin.dashboard.widgets.system-status',
            'order' => 90,
            'size' => 'full',
            'dataCallback' => function () {
                $pendingQueueJobs = 0;
                $failedQueueJobs = 0;
                try {
                    if (Schema::hasTable('jobs')) {
                        $pendingQueueJobs = DB::table('jobs')->count();
                    }
                    if (Schema::hasTable('failed_jobs')) {
                        $failedQueueJobs = DB::table('failed_jobs')->count();
                    }
                } catch (\Throwable) {
                }

                return [
                    'pendingQueueJobs' => $pendingQueueJobs,
                    'failedQueueJobs' => $failedQueueJobs,
                    'photoCount' => JobPhoto::count(),
                ];
            },
        ]);

        $registry->registerWidget('update-check', [
            'label' => 'Update Check',
            'view' => 'admin.dashboard.widgets.update-check',
            'order' => 100,
            'size' => 'full',
            'condition' => function () {
                if (! function_exists('sodium_crypto_sign_verify_detached')) {
                    return false;
                }
                try {
                    return (new SchneespurUpdater)->getState() !== null;
                } catch (\Throwable) {
                    return false;
                }
            },
            'dataCallback' => function () {
                return [
                    'updateState' => (new SchneespurUpdater)->getState(),
                ];
            },
        ]);

        $registry->registerWidget('alert-cards', [
            'label' => 'Alerts',
            'view' => 'admin.dashboard.widgets.alert-cards',
            'order' => 110,
            'size' => 'full',
            'condition' => function () {
                return Route::has('admin.alerts.index');
            },
            'dataCallback' => function () {
                $alertService = app(AlertService::class);
                $alertCounts = $alertService->counts();
                $alertTypes = [
                    'missing_gps' => [
                        'border' => 'border-red-500',
                        'bg' => 'bg-red-50',
                        'text' => 'text-red-700',
                        'count_text' => 'text-red-600',
                        'recentJobs' => $alertCounts['missing_gps'] > 0
                            ? $alertService->missingGpsQuery()->with('customer')->latest('started_at')->take(3)->get()
                            : collect(),
                    ],
                    'missing_weather' => [
                        'border' => 'border-orange-500',
                        'bg' => 'bg-orange-50',
                        'text' => 'text-orange-700',
                        'count_text' => 'text-orange-600',
                        'recentJobs' => $alertCounts['missing_weather'] > 0
                            ? $alertService->missingWeatherQuery()->with('customer')->latest('started_at')->take(3)->get()
                            : collect(),
                    ],
                    'overdue' => [
                        'border' => 'border-yellow-500',
                        'bg' => 'bg-yellow-50',
                        'text' => 'text-yellow-700',
                        'count_text' => 'text-yellow-600',
                        'recentJobs' => $alertCounts['overdue'] > 0
                            ? $alertService->overdueQuery()->with('customer')->latest('started_at')->take(3)->get()
                            : collect(),
                    ],
                ];

                return [
                    'alertCounts' => $alertCounts,
                    'alertTypes' => $alertTypes,
                ];
            },
        ]);
    }
}
