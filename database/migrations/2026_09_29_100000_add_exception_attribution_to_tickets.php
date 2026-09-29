<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('exception_source_file', 500)->nullable()->after('exception_server');
            $table->unsignedInteger('exception_source_line')->nullable()->after('exception_source_file');
            $table->string('exception_culprit_login', 100)->nullable()->after('exception_source_line');
            $table->string('exception_culprit_name', 150)->nullable()->after('exception_culprit_login');
            $table->string('exception_attribution_reason')->nullable()->after('exception_culprit_name');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'exception_source_file', 'exception_source_line', 'exception_culprit_login',
                'exception_culprit_name', 'exception_attribution_reason',
            ]);
        });
    }
};
