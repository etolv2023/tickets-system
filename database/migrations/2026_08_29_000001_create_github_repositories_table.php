<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F27. The repositories a ticket's work can live in.
 *
 * A row is configuration, not data pulled from GitHub: an administrator names
 * the four repositories once, and the sync writes nothing here except its own
 * timestamps. There is no destroy path — a repository is deactivated, never
 * removed — because every ticket_branches row points at one, and deleting the
 * row would erase the evidence those rows exist to hold.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_repositories', function (Blueprint $table) {
            $table->id();

            // What a human calls it in the dropdown. Free text: an Arabic label
            // reads better on a ticket than the slug does.
            $table->string('name');

            $table->string('owner', 100);
            $table->string('repo', 100);

            /*
             * SubtaskSide values, deliberately reusing the subtask vocabulary
             * rather than inventing a repo-side enum beside it. The point is a
             * later rule: "the backend person's points need a branch in a
             * backend repo" only stays expressible if both ends of that
             * sentence are drawn from the same list. Nullable — a repository
             * nobody has classified simply has no side.
             */
            $table->string('side', 20)->nullable();

            $table->string('default_branch', 100)->default('main');

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamp('last_synced_at')->nullable();

            // The last failure, kept so the admin screen can say WHY a
            // repository stopped reporting instead of only showing a stale date.
            $table->text('last_sync_error')->nullable();

            $table->timestamps();

            $table->unique(['owner', 'repo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_repositories');
    }
};
