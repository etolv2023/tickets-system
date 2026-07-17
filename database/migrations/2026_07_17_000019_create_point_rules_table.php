<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F18 — the matrix the admin edits. Changing a rule never affects points that
// were already awarded; the ledger keeps what it was paid.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_rules', function (Blueprint $table) {
            $table->id();
            $table->enum('ticket_type', ['bug', 'inquiry', 'feature', 'new_module', 'undefined']);
            $table->enum('scope', ['frontend', 'backend', 'both', 'any']);
            $table->enum('side', ['support', 'frontend', 'backend', 'tester']);
            $table->decimal('points', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ticket_type', 'scope', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
