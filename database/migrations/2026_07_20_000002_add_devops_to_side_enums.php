<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widens every side enum to accept 'devops'.
 *
 * Raw ALTER rather than the schema builder: Laravel cannot change a MySQL enum
 * fluently — `$table->enum(...)->change()` needs doctrine/dbal, which is not a
 * dependency here and would be a new package for four statements.
 *
 * ticket_work_logs.side is deliberately NOT in this list. DevOps holds no work
 * log; that is what "like the tester" means, and it is why the ticket still
 * reaches dev_done on the two development sides alone.
 */
return new class extends Migration
{
    /**
     * column => [accepted values, existing default].
     *
     * ★ The default is carried explicitly. MODIFY replaces the whole column
     * definition, so omitting it would silently drop ticket_subtasks.side's
     * DEFAULT 'other' and make every insert that leaves side out fail.
     */
    private const COLUMNS = [
        'ticket_subtasks.side' => [['frontend', 'backend', 'qa', 'support', 'devops', 'other'], 'other'],
        'point_rules.side' => [['support', 'frontend', 'backend', 'tester', 'devops'], null],
        'point_transactions.side' => [['support', 'frontend', 'backend', 'tester', 'devops'], null],
        'ratings.side' => [['support', 'frontend', 'backend', 'tester', 'devops'], null],
    ];

    public function up(): void
    {
        $this->apply(self::COLUMNS);
    }

    public function down(): void
    {
        // Rows carrying the new value would be truncated to '' by a narrowing
        // ALTER, so they go first — losing the row is honest, silently blanking
        // its side is not.
        foreach (array_keys(self::COLUMNS) as $target) {
            [$table] = explode('.', $target);
            DB::table($table)->where('side', 'devops')->delete();
        }

        $this->apply(array_map(
            fn (array $spec) => [array_values(array_diff($spec[0], ['devops'])), $spec[1]],
            self::COLUMNS
        ));
    }

    /** @param  array<string, array{0: array<int, string>, 1: string|null}>  $columns */
    private function apply(array $columns): void
    {
        foreach ($columns as $target => [$values, $default]) {
            [$table, $column] = explode('.', $target);

            $list = collect($values)->map(fn ($v) => "'{$v}'")->implode(',');
            $clause = $default === null ? '' : " DEFAULT '{$default}'";

            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` ENUM({$list}) NOT NULL{$clause}");
        }
    }
};
