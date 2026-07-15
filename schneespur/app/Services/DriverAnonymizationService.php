<?php

namespace App\Services;

use App\Events\User\UserAnonymized;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverAnonymizationService
{
    public function anonymize(User $driver, string $reason): void
    {
        if ($driver->isAnonymized()) {
            throw new DomainException('Driver is already anonymized.');
        }

        DB::transaction(function () use ($driver, $reason) {
            $driver->name = '';
            $driver->email = "anonymized-{$driver->id}@localhost";
            $driver->phone = null;
            $driver->owntracks_username = null;
            $driver->owntracks_password_hash = null;
            $driver->remember_token = null;
            $driver->anonymized_at = now();
            $driver->anonymization_reason = $reason;

            DB::table('users')->where('id', $driver->id)->update([
                'password' => Hash::make(Str::random(64)),
            ]);

            $driver->saveQuietly();

            DB::table('sessions')->where('user_id', $driver->id)->delete();
        });

        UserAnonymized::dispatch($driver, $reason);
    }
}
