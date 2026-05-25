<?php

use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\Admin\AdminJobController;
use App\Http\Controllers\Admin\BrandingController;
use App\Http\Controllers\Admin\HelpController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EmailSettingsController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\NotificationLogController;
use App\Http\Controllers\Admin\RetentionController;
use App\Http\Controllers\Admin\ArchivedDriverController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerObjectController;
use App\Http\Controllers\Admin\CustomerReportEmailController;
use App\Http\Controllers\Admin\DriverAnonymizationController;
use App\Http\Controllers\Admin\DsgvoAdminController;
use App\Http\Controllers\Admin\DriverController;
use App\Http\Controllers\Admin\CustomerPortalController;
use App\Http\Controllers\Admin\DriverCredentialController;
use App\Http\Controllers\Admin\CsvExportController;
use App\Http\Controllers\Admin\CustomerPdfController;
use App\Http\Controllers\Admin\DriverExportController;
use App\Http\Controllers\Admin\VehicleController;
use App\Http\Controllers\Admin\AdminWorkShiftController;
use App\Http\Controllers\Admin\CustomerReportController;
use App\Http\Controllers\Admin\DriverReportController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\OwntracksOverviewController;
use App\Http\Controllers\Admin\WeatherRetryController;
use App\Http\Controllers\Admin\AdminCronTaskController;
use App\Http\Controllers\Admin\AdminModuleApiTokenController;
use App\Http\Controllers\Admin\AdminModuleController;
use App\Http\Controllers\Admin\AdminModuleLogController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\UpdateSettingsController;
use App\Http\Controllers\Admin\BackupSettingsController;
use App\Http\Controllers\Admin\DispatchSettingsController;
use App\Http\Controllers\Admin\WeatherSettingsController;
use App\Http\Controllers\Admin\ManualJobController as AdminManualJobController;
use App\Http\Controllers\Driver\DriverJobController;
use App\Http\Controllers\Driver\DriverShiftController;
use App\Http\Controllers\Driver\DriverPhotoController;
use App\Http\Controllers\Driver\DriverJobHistoryController;
use App\Http\Controllers\Driver\ManualJobController as DriverManualJobController;
use App\Http\Controllers\DsgvoOnboardingController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalJobController;
use App\Http\Controllers\Portal\PortalNotificationController;
use App\Http\Controllers\Portal\PortalPdfController;
use App\Http\Controllers\Portal\PortalProfileController;
use App\Http\Controllers\ProfileController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! file_exists(storage_path('app/installed.lock'))) {
        return redirect()->route('install.welcome');
    }

    return redirect()->route('login');
});

if (! is_link(public_path('storage'))) {
    Route::get('/storage/{path}', \App\Http\Controllers\StorageFallbackController::class)
        ->where('path', '.*')
        ->name('storage.fallback');
}

Route::get('/manifest.webmanifest', function () {
    return response(json_encode([
        'name'             => brand(),
        'short_name'       => brand(),
        'display'          => 'standalone',
        'theme_color'      => '#1e293b',
        'background_color' => '#0f172a',
        'start_url'        => '/driver',
        'scope'            => '/',
        'icons'            => [
            ['src' => '/pwa-icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/pwa-icon-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/pwa-icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/pwa-icon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT))
        ->header('Content-Type', 'application/manifest+json');
})->name('manifest.webmanifest');

Route::get('/dashboard', function () {
    if (Auth::user()->isAdmin()) {
        return redirect()->route('admin.dashboard');
    }

    $user = Auth::user();
    $shiftActive = \App\Models\WorkShift::where('user_id', $user->id)
        ->whereNull('ended_at')
        ->exists();
    $customers = \App\Models\Customer::with('objects')->orderBy('name')->get();
    $vehicles = \App\Models\Vehicle::orderBy('name')->get();
    $defaultVehicleId = $user->default_vehicle_id;

    return view('driver.dashboard', [
        'shiftActive' => $shiftActive,
        'customers' => $customers,
        'vehicles' => $vehicles,
        'defaultVehicleId' => $defaultVehicleId,
    ]);
})->middleware(['auth', 'dsgvo'])->name('dashboard');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/dismiss-onboarding', [DashboardController::class, 'dismissOnboarding'])->name('dashboard.dismiss-onboarding');

    Route::resource('customers', CustomerController::class)->except(['show']);
    Route::post('/customers/geocode', [CustomerController::class, 'geocode'])->name('customers.geocode');

    Route::resource('customers.objects', CustomerObjectController::class)->except(['show'])->scoped();

    Route::post('/customers/{customer}/portal-access', [CustomerPortalController::class, 'setupAccess'])->name('customers.portal-access');
    Route::put('/customers/{customer}/portal-settings', [CustomerPortalController::class, 'updateSettings'])->name('customers.portal-settings');

    Route::get('/drivers/export-all', [DriverExportController::class, 'exportAll'])->name('drivers.export-all');
    Route::get('/drivers/archived', [ArchivedDriverController::class, 'index'])->name('drivers.archived');
    Route::resource('drivers', DriverController::class)->except(['show', 'destroy']);
    Route::get('/drivers/{driver}/export', [DriverExportController::class, 'exportSingle'])->name('drivers.export');
    Route::get('/drivers/{driver}/credentials', [DriverCredentialController::class, 'show'])->name('drivers.credentials');
    Route::post('/drivers/{driver}/rotate-credentials', [DriverCredentialController::class, 'rotate'])->name('drivers.rotate-credentials');
    Route::post('/drivers/{driver}/anonymize', DriverAnonymizationController::class)->name('drivers.anonymize');

    Route::resource('vehicles', VehicleController::class)->except(['show']);

    Route::resource('users', AdminUserController::class)->except(['show']);

    Route::get('/dsgvo', [DsgvoAdminController::class, 'index'])->name('dsgvo.index');
    Route::put('/dsgvo', [DsgvoAdminController::class, 'update'])->name('dsgvo.update');
    Route::post('/dsgvo/preview', [DsgvoAdminController::class, 'preview'])->name('dsgvo.preview');
    Route::get('/dsgvo/confirmations', [DsgvoAdminController::class, 'confirmations'])->name('dsgvo.confirmations');
    Route::get('/dsgvo/confirmations/{confirmation}', [DsgvoAdminController::class, 'showConfirmation'])->name('dsgvo.confirmations.show');

    Route::get('/customers/{customer}/objects/json', function (\App\Models\Customer $customer) {
        return $customer->objects()->orderBy('name')->get(['id', 'name', 'street', 'city']);
    })->name('customers.objects.json');

    Route::get('/jobs', [AdminJobController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/manual/create', [AdminManualJobController::class, 'create'])->name('jobs.manual.create');
    Route::post('/jobs/manual', [AdminManualJobController::class, 'store'])->name('jobs.manual.store');
    Route::get('/jobs/{serviceJob}/edit', [AdminJobController::class, 'edit'])->name('jobs.edit');
    Route::put('/jobs/{serviceJob}', [AdminJobController::class, 'update'])->name('jobs.update');
    Route::get('/jobs/{serviceJob}', [AdminJobController::class, 'show'])->name('jobs.show');
    Route::delete('/jobs/{serviceJob}', [AdminJobController::class, 'destroy'])->name('jobs.destroy');
    Route::get('/jobs/{serviceJob}/pdf', [AdminJobController::class, 'pdf'])->name('jobs.pdf');
    Route::post('/jobs/{serviceJob}/weather-retry/{moment}', WeatherRetryController::class)
        ->name('jobs.weather-retry')
        ->where('moment', 'start|end');

    Route::get('/workshifts', [AdminWorkShiftController::class, 'index'])->name('workshifts.index');
    Route::get('/workshifts/{workShift}', [AdminWorkShiftController::class, 'show'])->name('workshifts.show');

    Route::get('/overview/daily', [OverviewController::class, 'daily'])->name('overview.daily');
    Route::get('/overview/monthly', [OverviewController::class, 'monthly'])->name('overview.monthly');
    Route::get('/overview/day-detail', [OverviewController::class, 'dayDetail'])->name('overview.day-detail');
    Route::get('/overview/driver-report', [DriverReportController::class, 'index'])->name('overview.driver-report');
    Route::get('/overview/customer-report', [CustomerReportController::class, 'index'])->name('overview.customer-report');
    Route::post('/overview/customer-report/email', [CustomerReportEmailController::class, 'send'])->name('overview.customer-report.email');

    Route::get('/exports/csv', [CsvExportController::class, 'index'])->name('exports.csv');
    Route::get('/exports/csv/download', [CsvExportController::class, 'download'])->name('exports.csv.download');

    Route::get('/exports/customer-pdf', [CustomerPdfController::class, 'index'])->name('exports.customer-pdf');
    Route::post('/exports/customer-pdf', [CustomerPdfController::class, 'generate'])->name('exports.customer-pdf.generate');

    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/{serviceJob}/resolve', [AlertController::class, 'resolve'])->name('alerts.resolve');
    Route::post('/alerts/bulk-resolve', [AlertController::class, 'bulkResolve'])->name('alerts.bulk-resolve');

    Route::get('/owntracks/overview', OwntracksOverviewController::class)->name('owntracks.overview');

    Route::get('/help', [HelpController::class, 'index'])->name('help.index');
    Route::get('/help/{topic}', [HelpController::class, 'show'])->name('help.show');

    Route::get('/settings', function () {
        return view('admin.settings.index');
    })->name('settings.index');

    Route::get('/settings/branding', [BrandingController::class, 'edit'])->name('settings.branding');
    Route::post('/settings/branding', [BrandingController::class, 'update'])->name('settings.branding.update');
    Route::delete('/settings/branding/logo', [BrandingController::class, 'deleteLogo'])->name('settings.branding.delete-logo');

    Route::get('/settings/notification-log', [NotificationLogController::class, 'index'])->name('settings.notification-log');
    Route::get('/settings/email', [EmailSettingsController::class, 'edit'])->name('settings.email');
    Route::post('/settings/email', [EmailSettingsController::class, 'update'])->name('settings.email.update');
    Route::post('/settings/email/test', [EmailSettingsController::class, 'sendTest'])->name('settings.email.test');

    Route::get('/settings/company', [CompanyController::class, 'edit'])->name('settings.company');
    Route::post('/settings/company', [CompanyController::class, 'update'])->name('settings.company.update');
    Route::get('/settings/retention', [RetentionController::class, 'edit'])->name('settings.retention');
    Route::post('/settings/retention', [RetentionController::class, 'update'])->name('settings.retention.update');

    Route::get('/settings/dispatch', [DispatchSettingsController::class, 'edit'])->name('settings.dispatch');
    Route::post('/settings/dispatch', [DispatchSettingsController::class, 'update'])->name('settings.dispatch.update');

    Route::get('/settings/weather', [WeatherSettingsController::class, 'edit'])->name('settings.weather');
    Route::post('/settings/weather', [WeatherSettingsController::class, 'update'])->name('settings.weather.update');
    Route::post('/settings/weather/test', [WeatherSettingsController::class, 'testConnection'])->name('settings.weather.test');

    Route::get('/settings/backup', [BackupSettingsController::class, 'edit'])->name('settings.backup');
    Route::post('/settings/backup', [BackupSettingsController::class, 'update'])->name('settings.backup.update');

    Route::get('/settings/update', [UpdateSettingsController::class, 'edit'])->name('settings.update');
    Route::post('/settings/update', [UpdateSettingsController::class, 'update'])->name('settings.update.update');
    Route::post('/settings/update/check', [UpdateSettingsController::class, 'checkNow'])->name('settings.update.check');
    Route::post('/settings/update/install', [UpdateSettingsController::class, 'install'])->name('settings.update.install');

    Route::get('/crontasks', [AdminCronTaskController::class, 'index'])->name('crontasks.index');
    Route::post('/crontasks/{slug}/toggle', [AdminCronTaskController::class, 'toggle'])->name('crontasks.toggle');

    Route::get('/settings/modules', [AdminModuleController::class, 'index'])->name('settings.modules.index');
    Route::post('/settings/modules/{slug}/install', [AdminModuleController::class, 'install'])->name('settings.modules.install');
    Route::post('/settings/modules/{slug}/enable', [AdminModuleController::class, 'enable'])->name('settings.modules.enable');
    Route::post('/settings/modules/{slug}/disable', [AdminModuleController::class, 'disable'])->name('settings.modules.disable');
    Route::post('/settings/modules/{slug}/update', [AdminModuleController::class, 'update'])->name('settings.modules.update');
    Route::delete('/settings/modules/{slug}/remove', [AdminModuleController::class, 'remove'])->name('settings.modules.remove');

    Route::get('/settings/modules/{slug}/api-tokens', [AdminModuleApiTokenController::class, 'index'])->name('settings.modules.api-tokens.index');
    Route::get('/settings/modules/{slug}/api-tokens/create', [AdminModuleApiTokenController::class, 'create'])->name('settings.modules.api-tokens.create');
    Route::post('/settings/modules/{slug}/api-tokens', [AdminModuleApiTokenController::class, 'store'])->name('settings.modules.api-tokens.store');
    Route::delete('/settings/modules/{slug}/api-tokens/{token}', [AdminModuleApiTokenController::class, 'destroy'])->name('settings.modules.api-tokens.destroy');

    Route::get('/settings/modules/{slug}/logs', [AdminModuleLogController::class, 'index'])->name('settings.modules.logs');
});

Route::middleware(['auth', 'dsgvo', 'driver'])->prefix('driver')->name('driver.')->group(function () {
    Route::get('/', function () {
        $user = \Illuminate\Support\Facades\Auth::user();
        $shiftActive = \App\Models\WorkShift::where('user_id', $user->id)
            ->whereNull('ended_at')
            ->exists();
        $customers = \App\Models\Customer::with('objects')->orderBy('name')->get();
        $vehicles = \App\Models\Vehicle::orderBy('name')->get();
        $defaultVehicleId = $user->default_vehicle_id;

        return view('driver.dashboard', [
            'shiftActive' => $shiftActive,
            'customers' => $customers,
            'vehicles' => $vehicles,
            'defaultVehicleId' => $defaultVehicleId,
        ]);
    })->name('home');
    Route::post('/shift/start', [DriverShiftController::class, 'start'])->name('shift.start');
    Route::post('/shift/end', [DriverShiftController::class, 'end'])->name('shift.end');
    Route::post('/job/start', [DriverJobController::class, 'start'])->name('job.start');
    Route::post('/job/end', [DriverJobController::class, 'end'])->name('job.end');
    Route::get('/job/active', [DriverJobController::class, 'active'])->name('job.active');

    Route::post('/job/photo', [DriverPhotoController::class, 'store'])->name('job.photo.store');

    Route::get('/job/manual/create', [DriverManualJobController::class, 'create'])->name('job.manual.create');
    Route::post('/job/manual', [DriverManualJobController::class, 'store'])->name('job.manual.store');

    Route::get('/customers/{customer}/objects', function (\App\Models\Customer $customer) {
        return $customer->objects()->orderBy('name')->get(['id', 'name', 'street', 'city']);
    })->name('customer.objects');

    Route::get('/jobs', [DriverJobHistoryController::class, 'index'])->name('jobs.index');
    Route::get('/jobs/{job}', [DriverJobHistoryController::class, 'show'])->name('jobs.show');
});

Route::middleware('auth')->group(function () {
    Route::get('/onboarding/dsgvo', [DsgvoOnboardingController::class, 'show'])->name('onboarding.dsgvo');
    Route::post('/onboarding/dsgvo', [DsgvoOnboardingController::class, 'confirm'])->name('onboarding.dsgvo.confirm');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Portal (customer-facing)
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login']);
    });

    Route::middleware(['auth:customer', 'portal'])->group(function () {
        Route::get('/', PortalDashboardController::class)->name('home');
        Route::get('/jobs', [PortalJobController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{serviceJob}', [PortalJobController::class, 'show'])->name('jobs.show');
        Route::get('/jobs/{serviceJob}/pdf', [PortalPdfController::class, 'jobPdf'])->name('jobs.pdf');
        Route::get('/reports', [PortalPdfController::class, 'index'])->name('reports.index');
        Route::post('/reports/generate', [PortalPdfController::class, 'generate'])->name('reports.generate');
        Route::get('/notifications', [PortalNotificationController::class, 'index'])->name('notifications.index');
        Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');
    });
});

require __DIR__.'/auth.php';
