<?php

use App\Enums\BusinessStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('business_number', 32);
            $table->string('name', 150);
            $table->foreignId('owner_resident_id')->nullable()->constrained('residents')->restrictOnDelete();
            $table->string('owner_name', 255)->nullable();
            $table->foreignId('category_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('nature_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('ownership_type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('purok_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('street_address', 255)->nullable();
            $table->string('plate_number', 32)->nullable();
            $table->date('operating_since')->nullable();
            $table->decimal('capitalization', 12, 2)->nullable();
            $table->decimal('gross_receipts', 12, 2)->nullable();
            $table->string('status', 16)->default(BusinessStatus::Active->value);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['barangay_id', 'business_number']);
            $table->index(['barangay_id', 'status']);
            $table->index(['barangay_id', 'owner_resident_id']);
            $table->index(['barangay_id', 'category_id']);
            $table->index(['barangay_id', 'nature_id']);
            $table->index(['barangay_id', 'ownership_type_id']);
            $table->index(['barangay_id', 'purok_id']);
        });

        DB::statement('create unique index businesses_name_unique on businesses (barangay_id, lower(name))');
        DB::statement('alter table businesses add constraint businesses_owner_present check (owner_resident_id is not null or owner_name is not null)');
        DB::statement('alter table businesses add constraint businesses_amounts_not_negative check (coalesce(capitalization, 0) >= 0 and coalesce(gross_receipts, 0) >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
