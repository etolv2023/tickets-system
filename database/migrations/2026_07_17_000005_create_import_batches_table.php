<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F02. Every import leaves a record: what was uploaded, what it did, what failed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['companies', 'contacts', 'users']);
            $table->string('original_filename');
            $table->string('stored_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->enum('status', [
                'pending', 'validating', 'previewing', 'importing', 'completed', 'failed',
            ])->default('pending');
            // Row number + column + reason, so the failure report can be rebuilt.
            $table->json('errors')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
