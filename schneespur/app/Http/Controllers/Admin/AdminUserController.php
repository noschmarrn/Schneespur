<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('users.view');

        $users = User::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with('roles')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        Gate::authorize('users.view');

        return view('admin.users.create', [
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('users.edit');

        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        if ($request->validated('roles')) {
            $user->roles()->sync($request->validated('roles'));
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('user.flash_created', ['name' => $user->name]));
    }

    public function edit(User $user): View
    {
        Gate::authorize('users.view');

        $user->load('roles');

        return view('admin.users.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('users.edit');

        $user->update($request->safe()->only(['name', 'email']));

        if ($request->validated('password')) {
            $user->password = $request->validated('password');
            $user->save();
        }

        $newRoleIds = $request->validated('roles') ?? [];
        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole && $user->hasRole('admin') && ! in_array($adminRole->id, $newRoleIds)) {
            if (User::admins()->count() <= 1) {
                return redirect()
                    ->route('admin.users.edit', $user)
                    ->with('error', __('user.flash_last_admin_cannot_demote'));
            }
        }

        $user->roles()->sync($newRoleIds);
        $user->flushPermissionCache();

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('user.flash_updated', ['name' => $user->name]));
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('users.delete');

        if ($user->hasRole('admin') && User::admins()->count() <= 1) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', __('user.flash_last_admin_cannot_delete'));
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', __('user.flash_deleted', ['name' => $name]));
    }
}
