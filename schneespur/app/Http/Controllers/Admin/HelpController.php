<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

class HelpController extends Controller
{
    private const TOPICS = [
        'installation'  => 'help.topic_installation',
        'first-steps'   => 'help.topic_first_steps',
        'customers'     => 'help.topic_customers',
        'drivers'       => 'help.topic_drivers',
        'owntracks'     => 'help.topic_owntracks',
        'jobs'          => 'help.topic_jobs',
        'overview'      => 'help.topic_overview',
        'exports'       => 'help.topic_exports',
        'dsgvo'         => 'help.topic_dsgvo',
        'settings'      => 'help.topic_settings',
        'updates'       => 'help.topic_updates',
        'modules'       => 'help.topic_modules',
    ];

    public function index()
    {
        Gate::authorize('help.view');

        return view('admin.help.index', [
            'topics' => self::TOPICS,
        ]);
    }

    public function show(string $topic)
    {
        Gate::authorize('help.view');

        if (! array_key_exists($topic, self::TOPICS)) {
            abort(404);
        }

        return view('admin.help.show', [
            'topic'    => $topic,
            'topics'   => self::TOPICS,
            'langKey'  => self::TOPICS[$topic],
        ]);
    }
}
