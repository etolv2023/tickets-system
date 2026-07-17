<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// F10. "this blocks that" and "this is the same as that" — facts that otherwise
// get said in chat and lost.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('to_ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->enum('type', ['blocks', 'relates', 'duplicates', 'caused_by']);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            // Stored one way only; the view renders the inverse. F10
            $table->unique(['from_ticket_id', 'to_ticket_id', 'type']);
            $table->index('to_ticket_id');
        });

        DB::statement('ALTER TABLE ticket_links ADD CONSTRAINT chk_not_self CHECK (from_ticket_id <> to_ticket_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_links');
    }
};
