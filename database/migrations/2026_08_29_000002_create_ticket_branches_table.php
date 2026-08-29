<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F27. One branch, in one repository, belonging to one ticket.
 *
 * THIS TABLE IS A LEDGER, NOT A MIRROR. The sync never deletes a row. A branch
 * that stops existing on GitHub becomes state='deleted' and keeps its place,
 * because the moment a branch disappears is usually the moment its work was
 * merged — losing the record exactly then would drop the proof at the instant
 * it was earned. Nothing in this application removes a row here.
 *
 * unique(github_repository_id, name) rather than anything involving ticket_id:
 * one ticket legitimately has a branch in the backend repo AND one with the
 * same name in a frontend repo, and those are two independent rows. The pair
 * that must be unique is the branch's actual address on GitHub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_branches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('github_repository_id')->constrained()->restrictOnDelete();

            $table->string('name');
            $table->string('head_sha', 40)->nullable();

            // active  — seen in the last sync of its repository.
            // deleted — was seen once and is gone now. Marked, never removed.
            $table->enum('state', ['active', 'deleted'])->default('active');

            // auto   — the sync recognised the ticket number in the name.
            // manual — a person holding github.audit asserted it, and the
            //          assertion was checked against GitHub before acceptance.
            $table->enum('matched_by', ['auto', 'manual'])->default('auto');
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();

            // GitHub login of whoever wrote the head commit. Not a user_id: the
            // mapping to a system user goes through users.github_login, and it
            // is allowed to be missing.
            $table->string('author_login', 100)->nullable();
            $table->timestamp('last_commit_at')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('deleted_detected_at')->nullable();

            $table->timestamps();

            $table->unique(['github_repository_id', 'name']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_branches');
    }
};
