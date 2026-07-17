<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F04.2. Files live outside public/ under a uuid name; this table is the only
// map back to what the user called them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // Set when the file was attached to a comment rather than the ticket body.
            $table->foreignId('comment_id')->nullable()->constrained('ticket_comments')->cascadeOnDelete();
            $table->string('stored_name');
            $table->string('original_name');
            $table->string('thumbnail_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['ticket_id', 'comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
