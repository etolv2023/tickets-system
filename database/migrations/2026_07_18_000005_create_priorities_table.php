<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts priority from a fixed PHP enum + MySQL ENUM column into an
 * admin-manageable list, same pattern as ticket_statuses (2026_07_18_000001).
 *
 * sla_hours lives directly on the row now — no more a settings.sla_hours_{value}
 * side table. If an admin already customised those settings values on an
 * existing install, this migration reads them here so the number survives;
 * a fresh install just gets Priority's old hardcoded defaults.
 */
return new class extends Migration
{
    /** @var array<int, array{key: string, name_ar: string, color: string, default_hours: int}> */
    private const SYSTEM_PRIORITIES = [
        ['key' => 'urgent', 'name_ar' => 'عاجل', 'color' => 'red', 'default_hours' => 4],
        ['key' => 'high', 'name_ar' => 'مرتفع', 'color' => 'amber', 'default_hours' => 24],
        ['key' => 'medium', 'name_ar' => 'متوسط', 'color' => 'blue', 'default_hours' => 72],
        ['key' => 'low', 'name_ar' => 'منخفض', 'color' => 'slate', 'default_hours' => 168],
    ];

    public function up(): void
    {
        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name_ar', 100);
            $table->string('color', 20)->default('slate');
            $table->unsignedInteger('sla_hours');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        $hasSettings = Schema::hasTable('settings');

        foreach (self::SYSTEM_PRIORITIES as $position => $priority) {
            $hours = $priority['default_hours'];

            if ($hasSettings) {
                $existing = DB::table('settings')->where('key', "sla_hours_{$priority['key']}")->value('value');

                if ($existing !== null && ctype_digit((string) $existing)) {
                    $hours = (int) $existing;
                }
            }

            DB::table('priorities')->insert([
                'key' => $priority['key'],
                'name_ar' => $priority['name_ar'],
                'color' => $priority['color'],
                'sla_hours' => $hours,
                'is_system' => true,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($hasSettings) {
            DB::table('settings')->where('key', 'like', 'sla_hours_%')->delete();
        }

        // Strict ENUM until now, so every existing row's value is already one
        // of the 4 keys just inserted — the FK below can never fail on live data.
        DB::statement("ALTER TABLE tickets MODIFY priority VARCHAR(30) NOT NULL DEFAULT 'medium'");
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT fk_tickets_priority FOREIGN KEY (priority) REFERENCES priorities (`key`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_priority');
        DB::statement("ALTER TABLE tickets MODIFY priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium'");

        Schema::dropIfExists('priorities');
    }
};
