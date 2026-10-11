<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The system name gets an English form for the English UI. Seeded only where
 * the Arabic name is still the default: a company that renamed the system
 * keeps seeing its own name until an admin writes the English one, rather
 * than a generic "Ticketing System".
 */
return new class extends Migration
{
    public function up(): void
    {
        $default = DB::table('settings')->where('key', 'app_name')->value('value') === 'نظام التذاكر';

        if ($default && ! DB::table('settings')->where('key', 'app_name_en')->exists()) {
            DB::table('settings')->insert([
                'key' => 'app_name_en',
                'value' => 'Ticketing System',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('settings.all');
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'app_name_en')->delete();
        Cache::forget('settings.all');
    }
};
