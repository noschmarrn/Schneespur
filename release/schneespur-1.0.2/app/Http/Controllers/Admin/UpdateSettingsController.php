<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\SchneespurUpdater;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UpdateSettingsController extends Controller
{
    public function edit(): View
    {
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
        if ($hasSodium) {
            try {
                $preflight = (new SchneespurUpdater)->canInstall();
            } catch (\Throwable) {
            }
        }

        return view('admin.settings.update', [
            'hasSodium'      => $hasSodium,
            'autoCheck'      => Setting::get('auto_update_check', true),
            'currentVersion' => config('app.version', '1.0.0'),
            'state'          => $state,
            'preflight'      => $preflight,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Setting::set('auto_update_check', $request->boolean('auto_update_check'), 'bool');

        return redirect()->route('admin.settings.update')
            ->with('success', __('ui.saved'));
    }

    public function checkNow(): JsonResponse
    {
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
            $updater->install($zipPath, $manifest);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'      => false,
                'message' => __('update.install_failed', ['error' => $e->getMessage()]),
            ]);
        } finally {
            if (isset($zipPath)) {
                @unlink($zipPath);
            }
        }

        return response()->json([
            'ok'      => true,
            'message' => __('update.install_success', ['version' => $manifest['version']]),
        ]);
    }
}
