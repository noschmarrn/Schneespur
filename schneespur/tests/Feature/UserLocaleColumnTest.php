<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserLocaleColumnTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_users_table_has_locale_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'locale'));
    }

    public function test_locale_is_mass_assignable(): void
    {
        $user = User::create([
            'name' => 'Driver',
            'email' => 'd@test.local',
            'password' => Hash::make('password'),
            'locale' => 'cs',
        ]);

        $this->assertSame('cs', $user->fresh()->locale);
    }
}
