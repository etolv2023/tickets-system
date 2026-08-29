<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F27. A pull request, matched to a ticket through its head branch.
 *
 * Kept apart from ticket_branches rather than folded into it because a PR
 * outlives the branch it came from: GitHub keeps the pull request forever and
 * it still carries the head branch's name after that branch is gone. On any
 * repository where branches are deleted after merge, this table is the second,
 * independent copy of the same evidence.
 *
 * ticket_id is nullable: a PR whose head branch carries no ticket number is
 * still recorded, it just belongs to no ticket. Dropping it instead would mean
 * the admin screen could not show what failed to match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_pull_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('github_repository_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('number');
            $table->string('title');

            // GitHub reports open/closed plus a separate merged_at. Merging is
            // the fact anyone here cares about, so it is flattened into the
            // state rather than re-derived at every read site.
            $table->enum('state', ['open', 'closed', 'merged'])->default('open');
            $table->boolean('is_draft')->default(false);

            $table->string('author_login', 100)->nullable();
            $table->string('head_branch');
            $table->string('base_branch', 100);

            $table->timestamp('opened_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // GitHub's own updated_at. The sync asks for pull requests changed
            // since the newest value it holds, so a repository with 900 closed
            // PRs is not re-read in full every night.
            $table->timestamp('github_updated_at')->nullable();

            $table->timestamps();

            $table->unique(['github_repository_id', 'number']);
            $table->index('ticket_id');
            $table->index('github_updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_pull_requests');
    }
};
