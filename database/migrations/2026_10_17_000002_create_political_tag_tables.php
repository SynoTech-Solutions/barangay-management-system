<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('color_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('color_hex', 7);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['barangay_id', 'is_active', 'name']);
        });

        DB::statement('create unique index color_categories_barangay_name_unique on color_categories (barangay_id, lower(name))');

        Schema::create('resident_political_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('color_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tagged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['barangay_id', 'color_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_political_tags');
        Schema::dropIfExists('color_categories');
    }
};
