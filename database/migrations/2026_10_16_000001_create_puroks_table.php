<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('puroks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['barangay_id', 'is_active', 'name']);
        });

        DB::statement('create unique index puroks_barangay_name_unique on puroks (barangay_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('puroks');
    }
};
