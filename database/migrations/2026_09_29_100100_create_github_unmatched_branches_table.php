<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('github_unmatched_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('github_repository_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('head_sha', 40)->nullable();
            $table->enum('state', ['active', 'deleted'])->default('active');
            $table->string('author_login', 100)->nullable()->index();
            $table->timestamp('last_commit_at')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('deleted_detected_at')->nullable();
            $table->timestamps();

            $table->unique(['github_repository_id', 'name']);
            $table->index(['state', 'last_commit_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_unmatched_branches');
    }
};
