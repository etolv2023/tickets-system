<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('exception_culprit_id')->nullable()->after('exception_culprit_name')
                ->constrained('users')->nullOnDelete();
            $table->index(['type', 'exception_culprit_id']);
        });

        DB::table('users')->whereNotNull('github_login')->orderBy('id')->get()
            ->each(fn ($user) => DB::table('tickets')
                ->whereRaw('LOWER(exception_culprit_login) = ?', [mb_strtolower($user->github_login)])
                ->update(['exception_culprit_id' => $user->id]));
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['type', 'exception_culprit_id']);
            $table->dropConstrainedForeignId('exception_culprit_id');
        });
    }
};
