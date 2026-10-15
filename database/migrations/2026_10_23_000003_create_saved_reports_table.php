<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('dataset', 40);
            $table->jsonb('definition');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'is_active', 'dataset']);
        });
        DB::statement('create unique index saved_reports_name_unique on saved_reports (barangay_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
