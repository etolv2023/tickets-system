<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 29 permissions, seeded not hardcoded. PLAN.md § 2 / F00.3
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            // Groups the matrix UI renders under. "group" is reserved in MySQL;
            // the query builder quotes it.
            $table->string('group', 40)->index();
            $table->string('name_ar', 120);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
