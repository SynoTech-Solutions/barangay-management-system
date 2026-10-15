<?php

use App\Enums\ClearanceValidityMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barangay_profiles', function (Blueprint $table) {
            $table->foreignId('barangay_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('telephone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('id_card_ordinance_no', 128)->nullable();
            $table->string('clearance_validity_mode')->default(ClearanceValidityMode::EndOfYear->value);
            $table->unsignedSmallInteger('clearance_validity_months')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });

        Schema::create('signatories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->cascadeOnDelete();
            $table->string('office', 64);
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestamps();
            $table->unique(['barangay_id', 'office']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signatories');
        Schema::dropIfExists('barangay_profiles');
    }
};
