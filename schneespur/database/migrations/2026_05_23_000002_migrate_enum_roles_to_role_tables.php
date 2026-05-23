<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $adminId = DB::table('roles')->insertGetId([
            'slug' => 'admin',
            'name' => 'Administrator',
            'is_locked' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $driverId = DB::table('roles')->insertGetId([
            'slug' => 'driver',
            'name' => 'Fahrer',
            'is_locked' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $users = DB::table('users')->select('id', 'role')->get();

        $pivotRows = [];
        foreach ($users as $user) {
            $roleId = $user->role === 'admin' ? $adminId : $driverId;
            $pivotRows[] = ['user_id' => $user->id, 'role_id' => $roleId];
        }

        if (! empty($pivotRows)) {
            DB::table('role_user')->insert($pivotRows);
        }
    }

    public function down(): void
    {
        DB::table('role_user')->truncate();
        DB::table('roles')->truncate();
    }
};
