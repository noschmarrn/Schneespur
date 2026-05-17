<?php

namespace App\Services;

use App\Models\OwntracksCredentialEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OwntracksCredentialService
{
    /**
     * @return array{username: string, password: string}
     */
    public function generateCredentials(User $driver, User $actor): array
    {
        $isRotation = $driver->owntracks_username !== null;

        if (! $isRotation) {
            $driver->owntracks_username = 'driver-' . $driver->id . '-' . Str::lower(Str::random(4));
        }

        $plaintext = Str::random(16);

        $driver->owntracks_password_hash = Hash::make($plaintext);
        $driver->owntracks_password_revealed_at = null;
        $driver->save();

        OwntracksCredentialEvent::create([
            'driver_id' => $driver->id,
            'event' => $isRotation ? 'rotated' : 'created',
            'actor_user_id' => $actor->id,
        ]);

        return [
            'username' => $driver->owntracks_username,
            'password' => $plaintext,
        ];
    }
}
