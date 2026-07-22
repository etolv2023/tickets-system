<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Converts ticket-link type from a fixed PHP enum + MySQL ENUM
 * column into an admin-manageable list — same pattern as ticket_types. A link
 * type reads one way from the source ticket and another from the target, so it
 * carries both an `inverse_label_ar` (F10).
 *
 * The four originals are seeded here as system rows so `migrate` alone works on
 * a live DB. `blocks` carries code behaviour (the blocked marker, F10/F22), so
 * its key is fixed and it can't be deleted; the rest are plain relationships an
 * admin can rename or add to.
 */
return new class extends Migration
{
    /** @var array<int, array{key: string, name_ar: string, inverse_label_ar: string, color: string}> */
    private const SYSTEM_TYPES = [
        ['key' => 'blocks', 'name_ar' => 'بتبلوك', 'inverse_label_ar' => 'مبلوكة بـ', 'color' => 'red'],
        ['key' => 'relates', 'name_ar' => 'مرتبطة بـ', 'inverse_label_ar' => 'مرتبطة بـ', 'color' => 'slate'],
        ['key' => 'duplicates', 'name_ar' => 'مكررة لـ', 'inverse_label_ar' => 'متكررة في', 'color' => 'amber'],
        ['key' => 'caused_by', 'name_ar' => 'سببها', 'inverse_label_ar' => 'تسببت في', 'color' => 'plum'],
    ];

    public function up(): void
    {
        Schema::create('link_types', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name_ar', 100);
            // F10: how the same row reads from the OTHER ticket.
            $table->string('inverse_label_ar', 100);
            $table->string('color', 20)->default('slate');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();

        foreach (self::SYSTEM_TYPES as $position => $type) {
            DB::table('link_types')->insert($type + [
                'is_system' => true,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // The column was a strict ENUM, so every existing row's value is already
        // one of the keys just inserted. Raw SQL because doctrine/dbal isn't
        // installed.
        DB::statement('ALTER TABLE ticket_links MODIFY type VARCHAR(30) NOT NULL');
        DB::statement('ALTER TABLE ticket_links ADD CONSTRAINT fk_ticket_links_type FOREIGN KEY (type) REFERENCES link_types (`key`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ticket_links DROP FOREIGN KEY fk_ticket_links_type');
        DB::statement("ALTER TABLE ticket_links MODIFY type ENUM('blocks', 'relates', 'duplicates', 'caused_by') NOT NULL");

        Schema::dropIfExists('link_types');
    }
};
