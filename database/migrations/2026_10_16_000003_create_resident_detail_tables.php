<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_sector', function (Blueprint $table) {
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('lookup_values')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['resident_id', 'sector_id']);
            $table->index(['barangay_id', 'sector_id']);
        });

        Schema::create('family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('relationship_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('full_name', 320);
            $table->date('birthdate')->nullable();
            $table->foreignId('related_resident_id')->nullable()->constrained('residents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['barangay_id', 'resident_id']);
            $table->index('related_resident_id');
        });

        Schema::create('resident_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('course_id')->constrained('lookup_values')->restrictOnDelete();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['barangay_id', 'resident_id']);
        });

        Schema::create('resident_work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('lookup_values')->restrictOnDelete();
            $table->foreignId('employment_status_id')->constrained('lookup_values')->restrictOnDelete();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->boolean('is_current')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['barangay_id', 'resident_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_work_histories');
        Schema::dropIfExists('resident_educations');
        Schema::dropIfExists('family_members');
        Schema::dropIfExists('resident_sector');
    }
};
