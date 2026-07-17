<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F14. Someone on leave has zero capacity, and anything due on them that day
// should be flagged rather than quietly counted.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('type', ['annual', 'sick', 'other'])->default('annual');
            $table->string('note')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // The calendar asks "who is out between these two dates" per view.
            $table->index(['user_id', 'start_date', 'end_date']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_leaves');
    }
};
