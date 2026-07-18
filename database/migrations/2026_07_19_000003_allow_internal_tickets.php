<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Internal tickets: work raised by the team lead or the CTO rather than by a
 * customer.
 *
 * There is no separate "internal" flag. A ticket is internal exactly when it
 * has no company — one fact, one column, impossible to get out of sync with a
 * boolean that says otherwise. What replaces the customer is requested_by: the
 * colleague who asked for it.
 *
 * reporter_name goes nullable for the same reason. On a client ticket it is a
 * frozen snapshot of who reported it; on an internal one the requester is a
 * real user row and the snapshot means nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('requested_by')
                ->nullable()
                ->after('company_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Raw SQL for the two relaxations: doctrine/dbal (needed for
        // Blueprint::change()) isn't installed, and CLAUDE.md § 1 requires
        // asking before adding a package.
        DB::statement('ALTER TABLE tickets DROP FOREIGN KEY tickets_company_id_foreign');
        DB::statement('ALTER TABLE tickets MODIFY company_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)');

        DB::statement('ALTER TABLE tickets MODIFY reporter_name VARCHAR(150) NULL');
    }

    public function down(): void
    {
        // Internal tickets have no company to point at, so they cannot survive
        // the column becoming NOT NULL again.
        DB::table('tickets')->whereNull('company_id')->delete();

        DB::statement("UPDATE tickets SET reporter_name = '—' WHERE reporter_name IS NULL");
        DB::statement('ALTER TABLE tickets MODIFY reporter_name VARCHAR(150) NOT NULL');

        DB::statement('ALTER TABLE tickets DROP FOREIGN KEY tickets_company_id_foreign');
        DB::statement('ALTER TABLE tickets MODIFY company_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE tickets ADD CONSTRAINT tickets_company_id_foreign FOREIGN KEY (company_id) REFERENCES companies (id)');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
        });
    }
};
