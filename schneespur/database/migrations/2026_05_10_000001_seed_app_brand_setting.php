<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Setting::get('app_brand') === null) {
            Setting::set('app_brand', 'schneespur');
        }
    }

    public function down(): void
    {
        Setting::where('key', 'app_brand')->delete();
    }
};
