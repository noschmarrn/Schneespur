<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ArchivedDriverController extends Controller
{
    public function index(): View
    {
        Gate::authorize('drivers.view');

        $drivers = User::onlyAnonymized()
            ->orderByDesc('anonymized_at')
            ->paginate(25);

        return view('admin.drivers.archived', compact('drivers'));
    }
}
