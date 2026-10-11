<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every admin-managed list gets an English name beside its Arabic one, so the
 * English UI stops showing Arabic badges. Behaviour keeps keying off `key`;
 * the names are display only. An empty name_en falls back to the Arabic name.
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => key => English name */
    private const NAMES = [
        'ticket_types' => [
            'bug' => 'Bug',
            'inquiry' => 'Inquiry',
            'feature' => 'Feature',
            'new_module' => 'New module',
            'undefined' => 'Unspecified',
            'exception' => 'Exception',
        ],
        'priorities' => [
            'urgent' => 'Urgent',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
        ],
        'subtask_statuses' => [
            'todo' => 'To do',
            'in_progress' => 'In progress',
            'done' => 'Done',
            'blocked' => 'Blocked',
        ],
        'link_types' => [
            'blocks' => 'Blocks',
            'relates' => 'Relates to',
            'duplicates' => 'Duplicates',
            'caused_by' => 'Caused by',
        ],
        // Already has the column; a fresh install seeds roles after the
        // previous backfill ran, so RoleSeeder carries these too.
        'roles' => [
            'admin' => 'System administrator',
            'manager' => 'Team manager',
            'support' => 'Customer support',
            'frontend' => 'Frontend developer',
            'backend' => 'Backend developer',
            'devops' => 'DevOps engineer',
            'tester' => 'Quality assurance',
        ],
    ];

    /** @var array<string, string> link key => English inverse label */
    private const INVERSE = [
        'blocks' => 'Blocked by',
        'relates' => 'Relates to',
        'duplicates' => 'Duplicated by',
        'caused_by' => 'Caused',
    ];

    public function up(): void
    {
        foreach (['ticket_types', 'priorities', 'subtask_statuses', 'link_types'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('name_en', 100)->nullable()->after('name_ar');
            });
        }

        Schema::table('link_types', function (Blueprint $t) {
            $t->string('inverse_label_en', 100)->nullable()->after('inverse_label_ar');
        });

        Schema::table('labels', function (Blueprint $t) {
            $t->string('name_en', 50)->nullable()->after('name');
        });

        foreach (self::NAMES as $table => $names) {
            foreach ($names as $key => $nameEn) {
                // Only fill blanks: never overwrite a name an admin already set.
                DB::table($table)->where('key', $key)->whereNull('name_en')->update(['name_en' => $nameEn]);
            }
        }

        foreach (self::INVERSE as $key => $inverseEn) {
            DB::table('link_types')->where('key', $key)->update(['inverse_label_en' => $inverseEn]);
        }

        // These lists are cached forever as whole models; the deploy runs no
        // cache:clear, so the cached copies would keep lacking name_en.
        foreach ([
            'ticket_types.map', 'priorities.map', 'subtask_statuses.map', 'link_types.map',
            'ticket_statuses.map', 'labels.picker', 'roles.assignable_on_tickets', 'roles.by_id',
        ] as $key) {
            Cache::forget($key);
        }
    }

    public function down(): void
    {
        Schema::table('labels', fn (Blueprint $t) => $t->dropColumn('name_en'));
        Schema::table('link_types', fn (Blueprint $t) => $t->dropColumn(['name_en', 'inverse_label_en']));

        foreach (['ticket_types', 'priorities', 'subtask_statuses'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('name_en'));
        }
    }
};
