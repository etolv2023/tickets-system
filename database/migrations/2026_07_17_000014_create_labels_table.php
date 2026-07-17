<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F11. Colours come from the semantic palette only — a free colour picker would
// undo "every coloured pixel means something" (CLAUDE.md § 6).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            // Stores a token name (urgent, inquiry, …), never a hex value.
            $table->string('color', 20)->default('low');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('ticket_label', function (Blueprint $table) {
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['ticket_id', 'label_id']);
            $table->index('label_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_label');
        Schema::dropIfExists('labels');
    }
};
