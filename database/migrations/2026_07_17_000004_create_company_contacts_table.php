<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// F01. The person at the customer who reported the problem, plus their id in
// the customer's own ERP — the only handle both sides recognise.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('erp_employee_id', 50);
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // The same ERP id may legitimately repeat across two customers.
            $table->unique(['company_id', 'erp_employee_id']);
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contacts');
    }
};
