<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// The commitment to the customer. PLAN.md § 4.3
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 20)->unique();

            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('company_contacts')->nullOnDelete();
            // Frozen at creation: if the contact is edited or removed later, the
            // ticket still says who actually reported it. F03
            $table->string('reporter_name', 150);
            $table->string('reporter_erp_id', 50)->nullable();

            $table->string('title');
            $table->longText('description')->nullable();

            $table->enum('type', ['bug', 'inquiry', 'feature', 'new_module', 'undefined'])->default('undefined');
            $table->enum('scope', ['frontend', 'backend', 'both', 'inquiry', 'undefined'])->default('undefined');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', [
                'new', 'pending_approval', 'rejected', 'assigned', 'in_progress',
                'dev_done', 'testing', 'reopened', 'resolved', 'closed',
            ])->default('new');
            $table->string('module', 100)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_frontend_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_backend_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tester_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('approval_status', ['not_required', 'pending', 'approved', 'rejected'])
                ->default('not_required');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamp('reported_at');
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('client_notified_at')->nullable();
            $table->foreignId('client_notified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            // The double-spend guard for the points engine. F18
            $table->timestamp('points_awarded_at')->nullable();

            // Jira layer (phase 4). Stored rollups, never COUNT() at render time.
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('original_estimate_hours', 7, 2)->nullable();
            $table->decimal('spent_hours', 7, 2)->default(0);
            $table->unsignedInteger('subtasks_total')->default(0);
            $table->unsignedInteger('subtasks_done')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority', 'created_at']);
            $table->index('company_id');
            $table->index('assigned_frontend_id');
            $table->index('assigned_backend_id');
            $table->index('tester_id');
            $table->index('created_by');
            $table->index(['type', 'status']);
            $table->index('due_date');
            $table->index('sla_due_at');
        });

        // FULLTEXT can't be declared through the fluent builder for this combo,
        // and F21's search must not degrade to LIKE %...%.
        DB::statement('ALTER TABLE tickets ADD FULLTEXT ticket_search (title, description)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
