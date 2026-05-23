<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotificationLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('settings.view');

        $logs = NotificationLog::query()
            ->select('notification_logs.*')
            ->leftJoin('service_jobs', function ($join) {
                $join->on('notification_logs.notifiable_id', '=', 'service_jobs.id')
                    ->where('notification_logs.notifiable_type', '=', Job::class);
            })
            ->leftJoin('customers', 'service_jobs.customer_id', '=', 'customers.id')
            ->addSelect('customers.name as customer_name')
            ->when($request->status, fn ($q, $status) => $q->where('notification_logs.status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('notification_logs.type', $type))
            ->when($request->date_from, fn ($q, $date) => $q->where('notification_logs.created_at', '>=', $date))
            ->when($request->date_to, fn ($q, $date) => $q->where('notification_logs.created_at', '<=', $date . ' 23:59:59'))
            ->orderByDesc('notification_logs.created_at')
            ->paginate(25)
            ->withQueryString();

        return view('admin.settings.notification-log', compact('logs'));
    }
}
