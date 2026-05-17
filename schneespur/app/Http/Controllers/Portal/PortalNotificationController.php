<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Job;
use App\Models\NotificationLog;
use Illuminate\View\View;

class PortalNotificationController extends Controller
{
    public function index(): View
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        $logs = NotificationLog::query()
            ->where(function ($query) use ($customer) {
                $query->where(function ($q) use ($customer) {
                    $q->where('notifiable_type', Job::class)
                      ->whereIn('notifiable_id', $customer->serviceJobs()->select('id'));
                })->orWhere(function ($q) use ($customer) {
                    $q->where('notifiable_type', Customer::class)
                      ->where('notifiable_id', $customer->id);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('portal.notifications.index', compact('logs'));
    }
}
