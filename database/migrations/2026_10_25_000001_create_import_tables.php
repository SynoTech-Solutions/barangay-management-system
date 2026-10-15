<?php

use App\Enums\ImportRunStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default(ImportRunStatus::Running->value);
            $table->jsonb('options');
            $table->jsonb('report')->nullable();
            $table->string('problems_path')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operator_name', 100)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['barangay_id', 'started_at']);
        });

        Schema::create('import_id_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('entity', 64);
            $table->string('source_key', 191);
            $table->string('target_table', 64);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('import_run_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['barangay_id', 'entity', 'source_key']);
            $table->index(['barangay_id', 'target_table', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_id_map');
        Schema::dropIfExists('import_runs');
    }
};
