<?php

use App\Enums\BarangayStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('psgc_code', 10)->unique();
            $table->string('name');
            $table->string('status')->default(BarangayStatus::Active->value);
            $table->timestamps();
            $table->index(['municipality_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barangays');
    }
};
