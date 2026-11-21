<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Diagnostic\DiagnosticManager;
use App\Services\SchneespurUpdater;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UpdateSettingsController extends Controller
{
    public function edit(): View
    {
        Gate::authorize('settings.view');

        $hasSodium = function_exists('sodium_crypto_sign_verify_detached');
        $state     = null;

        if ($hasSodium) {
            try {
                $state = (new SchneespurUpdater)->getState();
            } catch (\Throwable) {
                // Config missing or corrupt — show page anyway
            }
        }

        $preflight = null;
        $pending   = null;
        if ($hasSodium) {
            try {
                $updater   = new SchneespurUpdater;
                $preflight = $updater->canInstall();
                $pending   = $updater->pendingUpdate();
            } catch (\Throwable) {
            }
        }

        return view('admin.settings.update', [
            'hasSodium'      => $hasSodium,
            'autoCheck'      => Setting::get('auto_update_check', true),
            'currentVersion' => config('app.version', '1.0.0'),
            'state'          => $state,
            'preflight'      => $preflight,
            'pending'        => $pending,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('settings.edit');

        Setting::set('auto_update_check', $request->boolean('auto_update_check'), 'bool');

        return redirect()->route('admin.settings.update')
            ->with('success', __('ui.saved'));
    }

    public function checkNow(): JsonResponse
    {
        Gate::authorize('settings.edit');

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return response()->json([
                'ok'      => false,
                'message' => __('update.sodium_missing'),
            ]);
        }

        try {
            $updater  = new SchneespurUpdater;
            $manifest = $updater->checkForUpdate();
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('update_check_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'UpdateSettingsController',
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }

            return response()->json([
                'ok'      => false,
                'message' => __('update.check_result_error', ['error' => $e->getMessage()]),
            ]);
        }

        if ($manifest === null) {
            return response()->json([
                'ok'      => true,
                'update'  => false,
                'message' => __('update.check_result_up_to_date', ['app_name' => config('app.name')]),
            ]);
        }

        $locale = app()->getLocale();

        return response()->json([
            'ok'          => true,
            'update'      => true,
            'message'     => __('update.check_result_update', ['version' => $manifest['version']]),
            'version'     => $manifest['version'],
            'changelog'   => $manifest['changelog'][$locale] ?? $manifest['changelog']['de'] ?? '',
            'name'        => $manifest['name'][$locale] ?? $manifest['name']['de'] ?? '',
            'description' => $manifest['description'][$locale] ?? $manifest['description']['de'] ?? '',
            'size_bytes'  => $manifest['size_bytes'] ?? null,
            'signed_at'   => $manifest['signed_at'] ?? null,
        ]);
    }

    public function install(): JsonResponse
    {
        Gate::authorize('settings.edit');

        if (! function_exists('sodium_crypto_sign_verify_detached')) {
            return response()->json([
                'ok'      => false,
                'message' => __('update.sodium_missing'),
            ]);
        }

        try {
            $updater  = new SchneespurUpdater;
            $manifest = $updater->checkForUpdate();
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('update_download_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'UpdateSettingsController',
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }

            return response()->json([
                'ok'      => false,
                'message' => __('update.check_result_error', ['error' => $e->getMessage()]),
            ]);
        }

        if ($manifest === null) {
            return response()->json([
                'ok'      => true,
                'update'  => false,
                'message' => __('update.check_result_up_to_date', ['app_name' => config('app.name')]),
            ]);
        }

        $preflight = $updater->canInstall();
        if (in_array(false, $preflight, true)) {
            return response()->json([
                'ok'      => false,
                'message' => __('update.preflight_fail'),
                'checks'  => $preflight,
            ]);
        }

        try {
            $zipPath = $updater->downloadAndVerifyZip($manifest);
            $secret  = $updater->stage($zipPath, $manifest);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('update_install_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'UpdateSettingsController',
                    'target_version' => $manifest['version'] ?? null,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }

            return response()->json([
                'ok'      => false,
                'message' => __('update.install_failed', ['error' => $e->getMessage()]),
            ]);
        } finally {
            if (isset($zipPath)) {
                @unlink($zipPath);
            }
        }

        // Stufe 2 muß ein FRISCHER Request sein: erst dort läuft der neue Code.
        // Das Cookie läßt den Browser des Admins durch den Wartungsmodus.
        return response()->json([
            'ok'      => true,
            'next'    => route('admin.settings.update.finalize'),
            'message' => __('update.install_finalizing'),
        ])->withCookie(MaintenanceModeBypassCookie::create($secret));
    }

    /**
     * Stufe 2 des Updates. VERSIONSÜBERGREIFENDER VERTRAG: Stufe 1 der alten
     * Version schickt den Browser hierher, beantwortet wird von der neuen.
     * Pfad (POST /admin/settings/update/finalize) und Antwortform
     * {ok, message, retry?} dürfen sich deshalb nie ändern.
     */
    public function finalize(): JsonResponse
    {
        Gate::authorize('settings.edit');

        try {
            $result = (new SchneespurUpdater)->finalize();
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'retry'   => true,
                'message' => $e->getMessage(),
            ]);
        }

        if ($result === null) {
            return response()->json([
                'ok'      => true,
                'message' => __('update.finalize_nothing_pending'),
            ]);
        }

        return response()->json([
            'ok'      => true,
            'message' => __('update.install_success', ['version' => $result['version']]),
        ]);
    }
}
