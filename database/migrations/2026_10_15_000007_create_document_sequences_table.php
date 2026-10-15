<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 64);
            $table->unsignedSmallInteger('period');
            $table->unsignedBigInteger('last_value');
            $table->timestamps();
            $table->primary(['barangay_id', 'kind', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
