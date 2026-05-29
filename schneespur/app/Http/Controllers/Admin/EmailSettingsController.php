<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Diagnostic\DiagnosticManager;
use App\Services\Installer\EnvFileWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class EmailSettingsController extends Controller
{
    private const PASSWORD_SENTINEL = '••••••••';

    private const MAIL_KEYS = [
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_SCHEME',
        'MAIL_USERNAME',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
    ];

    public function edit(EnvFileWriter $envWriter): View
    {
        Gate::authorize('settings.view');

        $config = [];
        foreach (self::MAIL_KEYS as $key) {
            $value = $envWriter->get($key) ?? '';
            if ($value === 'null') {
                $value = '';
            }
            if (preg_match('/\$\{(.+?)\}/', $value, $m)) {
                $value = env($m[1], $value);
            }
            $config[$key] = $value;
        }
        $config['MAIL_MAILER'] = $config['MAIL_MAILER'] ?: 'smtp';

        $envWritable = $envWriter->isWritable();

        $envContent = '';
        if (! $envWritable) {
            $lines = [];
            foreach (self::MAIL_KEYS as $key) {
                $lines[] = $key . '=' . $config[$key];
            }
            $lines[] = 'MAIL_PASSWORD=your-password-here';
            $envContent = implode("\n", $lines);
        }

        return view('admin.settings.email', [
            'config' => $config,
            'envWritable' => $envWritable,
            'envContent' => $envContent,
            'passwordSentinel' => self::PASSWORD_SENTINEL,
        ]);
    }

    public function update(Request $request, EnvFileWriter $envWriter): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $request->validate([
            'mail_mailer' => 'required|string',
            'mail_host' => 'required|string|max:255',
            'mail_port' => 'required|integer|min:1|max:65535',
            'mail_scheme' => 'required|in:null,tls,ssl',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        if (! $envWriter->isWritable()) {
            return redirect()->back()->with('error', __('notification.env_not_writable'));
        }

        $schemeInput = $request->input('mail_scheme');
        $schemeMap = ['tls' => '', 'ssl' => 'smtps', 'null' => ''];
        $mailScheme = $schemeMap[$schemeInput] ?? '';

        $values = [
            'MAIL_MAILER' => $request->input('mail_mailer'),
            'MAIL_HOST' => $request->input('mail_host'),
            'MAIL_PORT' => $request->input('mail_port'),
            'MAIL_SCHEME' => $mailScheme,
            'MAIL_USERNAME' => $request->input('mail_username', ''),
            'MAIL_FROM_ADDRESS' => $request->input('mail_from_address'),
            'MAIL_FROM_NAME' => $request->input('mail_from_name'),
        ];

        $password = $request->input('mail_password');
        if ($password !== null && $password !== '' && $password !== self::PASSWORD_SENTINEL) {
            $values['MAIL_PASSWORD'] = $password;
        }

        try {
            $envWriter->setMany($values);
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('env_write_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'EmailSettingsController',
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }

            return redirect()->back()->with('error', __('notification.env_not_writable'));
        }

        Artisan::call('config:clear');

        return redirect()->back()->with('success', __('notification.email_saved'));
    }

    public function sendTest(Request $request): RedirectResponse
    {
        Gate::authorize('settings.edit');

        $request->validate([
            'test_recipient' => 'required|email|max:255',
        ]);

        $recipient = $request->input('test_recipient');

        try {
            Mail::raw(__('notification.test_email_body', ['app_name' => brand()]), function ($message) use ($recipient) {
                $message->to($recipient)
                    ->subject(__('notification.test_email_subject', ['app_name' => brand()]));
            });
        } catch (\Throwable $e) {
            try {
                app(DiagnosticManager::class)->report('test_mail_failed', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                ], [
                    'source' => 'EmailSettingsController',
                    'recipient' => $recipient,
                ]);
            } catch (\Throwable) {
                // Never let diagnostic reporting break the original flow
            }

            return redirect()->back()->withInput()->with('error', __('notification.test_email_failed') . ': ' . $e->getMessage());
        }

        return redirect()->back()->with('success', __('notification.test_email_sent_to', ['email' => $recipient]));
    }
}
