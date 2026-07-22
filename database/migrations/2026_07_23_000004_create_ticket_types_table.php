<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-23) Converts ticket type from a fixed PHP enum + MySQL ENUM column
 * into an admin-manageable list — same pattern as ticket_statuses and
 * priorities. The admin can add a type, recolour it, pick its icon, and decide
 * whether it needs approval before assignment (F15), all without a code change.
 *
 * needs_approval replaces the hardcoded TicketType::needsApproval() (feature ||
 * new_module): each type now carries its own flag. The 4 original types are
 * seeded here inside the migration (not a Seeder) with exactly their old
 * appearance and approval behaviour, so `migrate` alone works on a live
 * database — every existing tickets.type value is already one of these keys.
 */
return new class extends Migration
{
    /** @var array<int, array{key: string, name_ar: string, color: string, icon: string, needs_approval: bool}> */
    private const SYSTEM_TYPES = [
        ['key' => 'bug', 'name_ar' => 'بج', 'color' => 'red', 'icon' => 'bug', 'needs_approval' => false],
        ['key' => 'inquiry', 'name_ar' => 'استفسار', 'color' => 'slate', 'icon' => 'inquiry', 'needs_approval' => false],
        ['key' => 'feature', 'name_ar' => 'فيتشر', 'color' => 'violet', 'icon' => 'feature', 'needs_approval' => true],
        ['key' => 'new_module', 'name_ar' => 'موديول جديد', 'color' => 'green', 'icon' => 'module', 'needs_approval' => true],
        // The "غير محدد" fallback stays a real row so an untyped legacy ticket
        // still renders, but it is a system row an admin can't delete.
        ['key' => 'undefined', 'name_ar' => 'غير محدد', 'color' => 'slate', 'icon' => 'undefined', 'needs_approval' => false],
    ];

    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name_ar', 100);
            // One of the semantic badge tokens only — no free colour (§ 6).
            $table->string('color', 20)->default('slate');
            // One of the icon names the <x-icon> component knows (whitelisted
            // in TicketTypeDefinition::ICONS).
            $table->string('icon', 40)->default('ticket');
            // F15: replaces the hardcoded feature||new_module check.
            $table->boolean('needs_approval')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (self::SYSTEM_TYPES as $position => $type) {
            DB::table('ticket_types')->insert($type + [
                'is_system' => true,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // The column was a strict ENUM, so every existing row's value is
        // already one of the keys just inserted — the FK below can never fail
        // on live data. Raw SQL because doctrine/dbal (needed for
        // Blueprint::change()) isn't installed (§ 1).
        DB::statement("ALTER TABLE tickets MODIFY type VARCHAR(30) NOT NULL DEFAULT 'undefined'");
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT fk_tickets_type FOREIGN KEY (type) REFERENCES ticket_types (`key`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_type');
        DB::statement("ALTER TABLE tickets MODIFY type ENUM('bug', 'inquiry', 'feature', 'new_module', 'undefined') NOT NULL DEFAULT 'undefined'");

        Schema::dropIfExists('ticket_types');
    }
};
