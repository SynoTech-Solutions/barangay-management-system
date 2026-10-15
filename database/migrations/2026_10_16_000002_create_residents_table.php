<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create extension if not exists pg_trgm');

        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('resident_number', 32);

            $table->foreignId('title_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->string('last_name', 100);
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('name_extension', 10)->nullable();
            $table->string('full_name', 320);
            $table->string('nickname', 100)->nullable();

            $table->date('birthdate')->nullable();
            $table->foreignId('birthplace_municipality_id')->nullable()->constrained('municipalities')->restrictOnDelete();
            $table->string('gender', 16)->nullable();
            $table->string('civil_status', 16)->nullable();

            $table->foreignId('nationality_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('religion_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('blood_type_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('body_type_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('health_issue_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('profession_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('income_bracket_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();

            $table->string('national_id', 32)->nullable();
            $table->string('drivers_license', 32)->nullable();
            $table->string('tin', 32)->nullable();
            $table->string('sss_gsis', 32)->nullable();
            $table->string('pagibig', 32)->nullable();
            $table->string('philhealth', 32)->nullable();

            $table->boolean('is_registered_voter')->default(false);
            $table->string('voter_id', 32)->nullable();
            $table->string('precinct_number', 16)->nullable();

            $table->boolean('is_living_with_parents')->default(false);
            $table->boolean('is_senior_citizen_member')->default(false);
            $table->boolean('is_pwd')->default(false);
            $table->boolean('is_single_parent')->default(false);
            $table->boolean('is_indigent')->default(false);

            $table->foreignId('purok_id')->nullable()->constrained('puroks')->restrictOnDelete();
            $table->string('street_address', 255)->nullable();
            $table->boolean('is_permanent_resident')->default(false);
            $table->foreignId('residence_type_id')->nullable()->constrained('lookup_values')->restrictOnDelete();
            $table->date('residing_since')->nullable();
            $table->text('alternate_address')->nullable();

            $table->string('mobile_number', 16)->nullable();
            $table->string('landline_number', 32)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('facebook_url', 255)->nullable();

            $table->boolean('is_deceased')->default(false);
            $table->date('deceased_on')->nullable();
            $table->string('cause_of_death', 255)->nullable();

            $table->foreignId('spouse_id')->nullable()->constrained('residents')->nullOnDelete();

            $table->string('photo_path')->nullable();
            $table->timestamp('photo_taken_at')->nullable();

            $table->boolean('is_incomplete')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['barangay_id', 'resident_number']);
            $table->index(['barangay_id', 'deleted_at', 'last_name', 'first_name']);
            $table->index(['barangay_id', 'purok_id']);
            $table->index('spouse_id');
        });

        DB::statement('create index residents_duplicate_key_index on residents (barangay_id, lower(last_name), lower(first_name), birthdate)');
        DB::statement('create index residents_full_name_trgm_index on residents using gin (full_name gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
