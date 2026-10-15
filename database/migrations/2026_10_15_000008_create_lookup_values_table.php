<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 64);
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['type', 'barangay_id', 'is_active']);
        });

        DB::statement('create unique index lookup_values_scope_type_label_unique on lookup_values (barangay_id, type, lower(label)) nulls not distinct');
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_values');
    }
};
