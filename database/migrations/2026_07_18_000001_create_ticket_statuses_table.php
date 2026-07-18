<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Converts ticket status from a fixed PHP enum + MySQL ENUM column into an
 * admin-manageable list, so a custom status (e.g. "بلوك") can be added without
 * a code change.
 *
 * The 10 existing statuses are seeded HERE, inside the migration, not in a
 * separate Seeder — this runs on `php artisan migrate` alone, which is what an
 * existing production database gets (not `migrate:fresh --seed`). By the time
 * the FK below is added, every value tickets.status could already contain
 * (it was a strict ENUM until this migration) exists as a row here, so no
 * existing ticket is ever left pointing at a status that doesn't exist.
 *
 * The automatic workflow engine (new→assigned→in_progress→...) stays keyed to
 * these 10 keys in code — that behaviour is tied to specific concepts (work
 * logs all done, a tester exists) and isn't something a dropdown should
 * redefine. What becomes dynamic is the ability to ADD further statuses for
 * manual use (F06's "غيّر الحالة" action) and the transition graph between
 * them, both from /admin/ticket-statuses.
 */
return new class extends Migration
{
    /** @var array<int, array{key: string, name_ar: string, color: string, is_open: bool}> */
    private const SYSTEM_STATUSES = [
        ['key' => 'new', 'name_ar' => 'جديدة', 'color' => 'slate', 'is_open' => true],
        ['key' => 'pending_approval', 'name_ar' => 'بانتظار الموافقة', 'color' => 'amber', 'is_open' => true],
        ['key' => 'rejected', 'name_ar' => 'مرفوضة', 'color' => 'red', 'is_open' => false],
        ['key' => 'assigned', 'name_ar' => 'موزعة', 'color' => 'slate', 'is_open' => true],
        ['key' => 'in_progress', 'name_ar' => 'جاري العمل', 'color' => 'blue', 'is_open' => true],
        ['key' => 'dev_done', 'name_ar' => 'تم التطوير', 'color' => 'amber', 'is_open' => true],
        ['key' => 'testing', 'name_ar' => 'تحت الاختبار', 'color' => 'blue', 'is_open' => true],
        ['key' => 'reopened', 'name_ar' => 'مرتجعة', 'color' => 'red', 'is_open' => true],
        ['key' => 'resolved', 'name_ar' => 'تم الحل', 'color' => 'green', 'is_open' => false],
        ['key' => 'closed', 'name_ar' => 'مغلقة', 'color' => 'green', 'is_open' => false],
    ];

    /** @var array<string, array<int, string>> exactly TicketWorkflowService's old TRANSITIONS */
    private const SYSTEM_TRANSITIONS = [
        'new' => ['pending_approval', 'assigned', 'resolved', 'rejected'],
        'pending_approval' => ['assigned', 'rejected'],
        'assigned' => ['in_progress', 'resolved', 'new'],
        'in_progress' => ['dev_done', 'assigned'],
        'dev_done' => ['testing', 'resolved', 'in_progress'],
        'testing' => ['resolved', 'reopened'],
        'reopened' => ['in_progress', 'assigned', 'resolved'],
        'resolved' => ['closed', 'reopened'],
        'closed' => ['reopened'],
    ];

    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->unique();
            $table->string('name_ar', 100);
            // One of the semantic badge tokens only — no free colour (§ 6).
            $table->string('color', 20)->default('slate');
            // Seeded by this migration; protected from deletion and from a
            // key change (nothing else here is editable by the app, but § 3
            // frowns on unused guard columns, so this flag earns its keep by
            // gating both in the controller).
            $table->boolean('is_system')->default(false);
            // "Still owed to the customer" — replaces TicketStatus::isOpen()
            // and is what lets a custom status participate correctly in the
            // calendar, "اليوم", and load-report queries without code changes.
            $table->boolean('is_open')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('ticket_status_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_status_id')->constrained('ticket_statuses')->cascadeOnDelete();
            $table->foreignId('to_status_id')->constrained('ticket_statuses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['from_status_id', 'to_status_id']);
        });

        $now = now();

        foreach (self::SYSTEM_STATUSES as $position => $status) {
            DB::table('ticket_statuses')->insert($status + [
                'is_system' => true,
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $ids = DB::table('ticket_statuses')->pluck('id', 'key');

        foreach (self::SYSTEM_TRANSITIONS as $fromKey => $toKeys) {
            foreach ($toKeys as $toKey) {
                DB::table('ticket_status_transitions')->insert([
                    'from_status_id' => $ids[$fromKey],
                    'to_status_id' => $ids[$toKey],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // The column was a strict ENUM, so every existing row's value is
        // already one of the 10 keys just inserted above — the FK below can
        // never fail on live data. Raw SQL because doctrine/dbal (needed for
        // Blueprint::change()) isn't installed, and CLAUDE.md § 1 requires
        // asking before adding a package.
        DB::statement("ALTER TABLE tickets MODIFY status VARCHAR(30) NOT NULL DEFAULT 'new'");
        // `key` is a MySQL reserved word — must be backtick-quoted on both sides.
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT fk_tickets_status FOREIGN KEY (status) REFERENCES ticket_statuses (`key`)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_status');
        DB::statement("ALTER TABLE tickets MODIFY status ENUM(
            'new', 'pending_approval', 'rejected', 'assigned', 'in_progress',
            'dev_done', 'testing', 'reopened', 'resolved', 'closed'
        ) NOT NULL DEFAULT 'new'");

        Schema::dropIfExists('ticket_status_transitions');
        Schema::dropIfExists('ticket_statuses');
    }
};
