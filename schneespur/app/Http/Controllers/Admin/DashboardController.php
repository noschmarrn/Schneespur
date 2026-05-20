<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Extension\DashboardWidgetRegistry;
use App\Services\Extension\FilterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function dismissOnboarding(): RedirectResponse
    {
        Setting::set('onboarding_dismissed', '1');

        return redirect()->route('admin.dashboard');
    }

    public function index(DashboardWidgetRegistry $widgetRegistry, FilterRegistry $filterRegistry): View
    {
        $widgets = $widgetRegistry->getWidgets();
        $widgets = $filterRegistry->apply('schneespur.dashboard.kpis', $widgets);

        return view('admin.dashboard', compact('widgets'));
    }
}
