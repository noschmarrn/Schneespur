<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Manually entered jobs were stored as if the operator's local input were UTC.
 * This moves them to where they were always meant to be.
 *
 * The shift is computed per record, from that record's own date: Europe/Berlin
 * is UTC+1 in January and UTC+2 in July, so one fixed number would move half
 * the data the wrong way.
 *
 * Automatic jobs are untouched — their timestamps come from GPS reports and
 * were always genuinely UTC.
 *
 * Knowingly accepted (design decision 5): an operator who already knew the
 * display was wrong and compensated while typing gets moved a second time.
 * That is indistinguishable from correct input and must not be guessed at.
 */
return new class extends Migration
{
    /**
     * Resolved from the settings table directly rather than through
     * App\Support\Timezones: a migration can run at a point where the service
     * provider's boot has not applied the config, and reading it there would
     * make the correction depend on boot order.
     *
     * Order matches Timezones::resolve(): the stored setting, then the env
     * value, then the historical default.
     */
    private function zone(): DateTimeZone
    {
        $stored = null;

        try {
            $stored = DB::table('settings')->where('key', 'display_timezone')->value('value');
        } catch (\Throwable) {
            // No settings table yet — fall through to the env value.
        }

        foreach ([$stored, env('APP_DISPLAY_TIMEZONE')] as $candidate) {
            if (is_string($candidate) && $candidate !== ''
                && in_array($candidate, DateTimeZone::listIdentifiers(), true)) {
                return new DateTimeZone($candidate);
            }
        }

        return new DateTimeZone('Europe/Berlin');
    }

    /**
     * @param  'up'|'down'  $direction
     */
    private function shift(string $direction): void
    {
        $zone = $this->zone();
        $utc = new DateTimeZone('UTC');
        $touched = [];

        // Chunked because this runs inside a web request on shared hosting,
        // where there is no CLI: an operation with five winters of manual
        // entries must not walk into the memory limit.
        DB::table('service_jobs')
            ->where('is_manual', true)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($zone, $utc, $direction, &$touched) {
                foreach ($rows as $row) {
                    $update = [];

                    foreach (['started_at', 'ended_at'] as $column) {
                        if (empty($row->{$column})) {
                            continue;
                        }

                        $stored = new DateTimeImmutable((string) $row->{$column}, $utc);

                        // up:   the stored wall-clock reading was local all along,
                        //       so re-read it in the local zone and convert to UTC.
                        // down: the stored value is real UTC; read its local wall
                        //       clock and write that back as if it were UTC.
                        $corrected = $direction === 'up'
                            ? (new DateTimeImmutable($stored->format('Y-m-d H:i:s'), $zone))->setTimezone($utc)
                            : new DateTimeImmutable($stored->setTimezone($zone)->format('Y-m-d H:i:s'), $utc);

                        $update[$column] = $corrected->format('Y-m-d H:i:s');
                    }

                    if ($update !== []) {
                        DB::table('service_jobs')->where('id', $row->id)->update($update);
                        $touched[] = $row->id;
                    }
                }
            });

        Log::info('Manual jobs shifted to the operation timezone', [
            'direction' => $direction,
            'timezone' => $zone->getName(),
            'count' => count($touched),
            'ids' => $touched,
        ]);
    }

    public function up(): void
    {
        $this->shift('up');
    }

    public function down(): void
    {
        $this->shift('down');
    }
};
