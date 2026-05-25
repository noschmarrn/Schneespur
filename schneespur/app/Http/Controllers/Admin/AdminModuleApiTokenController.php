<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\ModuleApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminModuleApiTokenController extends Controller
{
    public function index(string $slug): View
    {
        Gate::authorize('settings.view');

        $module = Module::where('slug', $slug)->firstOrFail();
        $tokens = $module->apiTokens()->orderByDesc('created_at')->get();

        return view('admin.settings.modules.api-tokens.index', [
            'module' => $module,
            'tokens' => $tokens,
        ]);
    }

    public function create(string $slug): View
    {
        Gate::authorize('settings.edit');

        $module = Module::where('slug', $slug)->firstOrFail();

        return view('admin.settings.modules.api-tokens.create', [
            'module' => $module,
        ]);
    }

    public function store(Request $request, string $slug): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $module = Module::where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $plaintext = Str::random(40);

        $token = $module->apiTokens()->create([
            'module_slug' => $module->slug,
            'name' => $validated['name'],
            'token_hash' => hash('sha256', $plaintext),
            'expires_at' => $validated['expires_at'] ?? null,
        ]);

        Log::info('Module API token created', [
            'module_slug' => $module->slug,
            'token_id' => $token->id,
            'token_name' => $token->name,
        ]);

        return redirect()
            ->route('admin.settings.modules.api-tokens.index', $slug)
            ->with('success', __('modules.token_created', ['name' => $token->name]))
            ->with('plaintext_token', $plaintext);
    }

    public function destroy(string $slug, ModuleApiToken $token): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $module = Module::where('slug', $slug)->firstOrFail();

        if ($token->module_slug !== $module->slug) {
            abort(404);
        }

        $tokenName = $token->name;
        $tokenId = $token->id;
        $token->delete();

        Log::info('Module API token revoked', [
            'module_slug' => $module->slug,
            'token_id' => $tokenId,
            'token_name' => $tokenName,
        ]);

        return redirect()
            ->route('admin.settings.modules.api-tokens.index', $slug)
            ->with('success', __('modules.token_revoked', ['name' => $tokenName]));
    }
}
