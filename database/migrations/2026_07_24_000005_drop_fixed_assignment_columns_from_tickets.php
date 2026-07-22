<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ★ (2026-07-24) Final phase of role-based distribution.
 *
 * The four fixed assignment columns are gone. Every assignment they held was
 * copied into ticket_role_assignments by the backfill (000003), and all the
 * read/write paths now go through that table, so these columns have no readers
 * left. Dropping them is what makes the model honestly role-based rather than
 * "role-based plus four special cases".
 *
 * Reversible with its data intact: down() re-creates the columns and repopulates
 * them from the role assignments, keyed by the built-in role keys — the exact
 * inverse of the backfill.
 */
return new class extends Migration
{
    /** tickets column => the role key its assignee lives under in role assignments */
    private const COLUMN_ROLE = [
        'assigned_frontend_id' => 'frontend',
        'assigned_backend_id' => 'backend',
        'tester_id' => 'tester',
        'devops_id' => 'devops',
    ];

    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // Drop the FK (and its backing index) and the plain index, then the
            // column itself — MySQL refuses to drop a column an index still names.
            foreach (array_keys(self::COLUMN_ROLE) as $column) {
                $table->dropForeign([$column]);
                $table->dropIndex([$column]);
            }

            $table->dropColumn(array_keys(self::COLUMN_ROLE));
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('assigned_frontend_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_backend_id')->nullable()->after('assigned_frontend_id')->constrained('users')->nullOnDelete();
            $table->foreignId('tester_id')->nullable()->after('assigned_backend_id')->constrained('users')->nullOnDelete();
            $table->foreignId('devops_id')->nullable()->after('tester_id')->constrained('users')->nullOnDelete();

            $table->index('assigned_frontend_id');
            $table->index('assigned_backend_id');
            $table->index('tester_id');
            $table->index('devops_id');
        });

        // Repopulate each column from the role assignment that mirrors it.
        foreach (self::COLUMN_ROLE as $column => $roleKey) {
            $roleId = DB::table('roles')->where('key', $roleKey)->value('id');
            if ($roleId === null) {
                continue;
            }

            DB::table('ticket_role_assignments')
                ->where('role_id', $roleId)
                ->orderBy('ticket_id')
                ->each(function ($assignment) use ($column) {
                    DB::table('tickets')
                        ->where('id', $assignment->ticket_id)
                        ->update([$column => $assignment->user_id]);
                });
        }
    }
};
