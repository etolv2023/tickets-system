<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A fourth person on a ticket: DevOps.
 *
 * Modelled on tester_id, not on the two assigned_*_id columns — DevOps holds no
 * work log, so it never gates the ticket's arrival at dev_done. It is somebody
 * the ticket is assigned to, who sees it, is notified about it, and earns on
 * subtasks of their own side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('devops_id')->nullable()->after('tester_id')
                ->constrained('users')->nullOnDelete();

            // Same reason the other three are indexed: scopeVisibleTo() ORs
            // across all of them on every list query.
            $table->index('devops_id');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['devops_id']);
            $table->dropIndex(['devops_id']);
            $table->dropColumn('devops_id');
        });
    }
};
