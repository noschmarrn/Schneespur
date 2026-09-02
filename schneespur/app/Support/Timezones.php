<?php

namespace App\Support;

use DateTimeZone;
use Illuminate\Support\Facades\Config;

/**
 * Everything the application knows about time zones.
 *
 * The operator's zone is stored as a setting and has to reach the config,
 * because that is what Formatter::formatMoment() and Job::localStartedAt()
 * read. Until 1.2.1 nothing made that trip: the installer wrote the setting
 * and config/app.php only ever read env(), so choosing a zone in the wizard
 * changed nothing at all.
 *
 * Resolution lives here rather than inside a closure in AppServiceProvider so
 * that it can be called directly — by a test, and by the migration that has to
 * resolve the same zone at a point where the service provider's boot has not
 * necessarily run.
 */
final class Timezones
{
    /**
     * The zone this installation runs in.
     *
     * Order: the stored setting, then APP_DISPLAY_TIMEZONE, then the
     * historical default. An unusable value at any step falls through to the
     * next rather than being applied — a bad identifier would throw inside
     * Formatter::formatMoment(), which is to say on every page carrying a date.
     */
    public static function resolve(): string
    {
        $stored = null;

        try {
            $stored = \App\Models\Setting::get('display_timezone');
        } catch (\Throwable) {
            // Settings table unavailable (installer / early boot / migration).
        }

        if (is_string($stored) && self::isValid($stored)) {
            return $stored;
        }

        $fromEnv = (string) env('APP_DISPLAY_TIMEZONE', '');

        if (self::isValid($fromEnv)) {
            return $fromEnv;
        }

        return 'Europe/Berlin';
    }

    /**
     * Mirror the resolved zone into the config, at runtime.
     *
     * Never baked into a config cache — the same reasoning as for
     * mail.from.name in AppServiceProvider.
     */
    public static function apply(): void
    {
        Config::set('app.display_timezone', self::resolve());
    }

    public static function isValid(string $identifier): bool
    {
        return $identifier !== '' && in_array($identifier, DateTimeZone::listIdentifiers(), true);
    }
}
