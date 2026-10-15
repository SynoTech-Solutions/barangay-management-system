<?php

use App\Enums\BlotterStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blotters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('blotter_number', 32);
            $table->foreignId('incident_type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->timestamp('incident_at');
            $table->string('place', 255)->nullable();
            $table->text('narrative');
            $table->string('status', 16)->default(BlotterStatus::Open->value);
            $table->timestamp('reported_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'blotter_number']);
            $table->index(['barangay_id', 'status', 'incident_at']);
        });

        Schema::create('blotter_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('blotter_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->foreignId('resident_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['barangay_id', 'blotter_id']);
            $table->index(['barangay_id', 'resident_id', 'role']);
        });

        DB::statement('alter table blotter_parties add constraint blotter_parties_identity_present check (resident_id is not null or name is not null)');

        Schema::create('blotter_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('blotter_id')->constrained()->restrictOnDelete();
            $table->string('path', 255);
            $table->string('mime_type', 64);
            $table->unsignedInteger('size_bytes');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['barangay_id', 'blotter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blotter_photos');
        Schema::dropIfExists('blotter_parties');
        Schema::dropIfExists('blotters');
    }
};
