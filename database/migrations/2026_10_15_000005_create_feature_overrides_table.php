<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('flag', 64);
            $table->boolean('enabled');
            $table->string('reason', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('create unique index feature_overrides_barangay_flag_unique on feature_overrides (barangay_id, flag) nulls not distinct');
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_overrides');
    }
};
