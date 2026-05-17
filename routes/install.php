<?php

use App\Http\Controllers\InstallerController;
use Illuminate\Support\Facades\Route;

Route::middleware('installer')->prefix('install')->name('install.')->group(function () {

    Route::get('/locale/{locale}', [InstallerController::class, 'switchLocale'])
        ->name('locale.switch')
        ->where('locale', 'de|en')
        ->middleware('throttle:20,1');

    Route::get('/', [InstallerController::class, 'showWelcome'])->name('welcome');
    Route::post('/welcome', [InstallerController::class, 'processWelcome'])->name('welcome.process')->middleware('throttle:10,1');

    Route::get('/database', [InstallerController::class, 'showDatabase'])->name('database');
    Route::post('/database', [InstallerController::class, 'storeDatabase'])->name('database.store')->middleware('throttle:10,1');

    Route::get('/preflight', [InstallerController::class, 'showPreflight'])->name('preflight');
    Route::post('/preflight', [InstallerController::class, 'processPreflight'])->name('preflight.process')->middleware('throttle:10,1');

    Route::get('/migrations', [InstallerController::class, 'showMigrations'])->name('migrations');
    Route::post('/migrations', [InstallerController::class, 'runMigrations'])->name('migrations.run')->middleware('throttle:10,1');

    Route::get('/config', [InstallerController::class, 'showConfig'])->name('config');
    Route::post('/config', [InstallerController::class, 'storeConfig'])->name('config.store')->middleware('throttle:10,1');

    Route::get('/storage', [InstallerController::class, 'showStorage'])->name('storage');
    Route::post('/storage', [InstallerController::class, 'runStorage'])->name('storage.run')->middleware('throttle:10,1');

    Route::get('/admin', [InstallerController::class, 'showAdmin'])->name('admin');
    Route::post('/admin', [InstallerController::class, 'storeAdmin'])->name('admin.store')->middleware('throttle:10,1');

    Route::get('/mail', [InstallerController::class, 'showMail'])->name('mail');
    Route::post('/mail', [InstallerController::class, 'sendTestMail'])->name('mail.send')->middleware('throttle:10,1');
    Route::post('/mail/skip', [InstallerController::class, 'skipMail'])->name('mail.skip')->middleware('throttle:10,1');

    Route::get('/cron', [InstallerController::class, 'showCron'])->name('cron');
    Route::post('/cron/test', [InstallerController::class, 'testCron'])->name('cron.test')->middleware('throttle:10,1');
    Route::post('/cron/skip', [InstallerController::class, 'skipCron'])->name('cron.skip')->middleware('throttle:10,1');

    Route::get('/done', [InstallerController::class, 'showDone'])->name('done');
});
