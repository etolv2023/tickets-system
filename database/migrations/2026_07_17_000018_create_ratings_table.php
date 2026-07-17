<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F17. Optional by design — a mandatory rating produces 8/10 for everyone.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ratee_id')->constrained('users')->cascadeOnDelete();
            $table->enum('side', ['support', 'frontend', 'backend', 'tester']);
            $table->unsignedTinyInteger('score');
            $table->string('comment')->nullable();
            $table->foreignId('rated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('rated_at')->nullable();

            // One rating per person per side per ticket. F17
            $table->unique(['ticket_id', 'ratee_id', 'side']);
            $table->index('ratee_id');
        });

        // F17 asks for the range in the DB as well as the Request — the Request
        // can be bypassed by a console command, the CHECK cannot.
        DB::statement('ALTER TABLE ratings ADD CONSTRAINT chk_score CHECK (score >= 1 AND score <= 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
