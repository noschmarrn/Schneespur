<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $customers = DB::table('customers')->get();

        foreach ($customers as $customer) {
            DB::table('customer_objects')->insert([
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'street' => $customer->street,
                'zip' => $customer->zip,
                'city' => $customer->city,
                'lat' => $customer->lat,
                'lon' => $customer->lon,
                'contact_name' => $customer->contact_name,
                'contact_email' => $customer->email,
                'contact_phone' => $customer->phone,
                'price_amount_cents' => $customer->price_amount_cents,
                'price_unit' => $customer->price_unit,
                'plow_threshold_cm' => $customer->plow_threshold_cm,
                'salt_enabled' => $customer->salt_enabled,
                'site_notes' => $customer->site_notes,
                'access_notes' => $customer->access_notes,
                'notify_recipients' => 'customer',
                'auto_notify_email' => $customer->auto_notify_email,
                'notification_email' => $customer->notification_email,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('customer_objects')->truncate();
    }
};
