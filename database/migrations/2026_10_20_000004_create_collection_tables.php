<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'is_active']);
        });

        DB::statement('create unique index collection_types_name_unique on collection_types (barangay_id, lower(name))');

        Schema::create('other_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('collection_number', 32);
            $table->foreignId('collection_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payer_name', 255);
            $table->string('or_number', 32);
            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('remarks', 255)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'collection_number']);
            $table->index(['barangay_id', 'paid_on']);
            $table->index(['barangay_id', 'collection_type_id']);
            $table->index(['barangay_id', 'resident_id']);
        });

        DB::statement('create unique index other_collections_or_number_unique on other_collections (barangay_id, or_number) where cancelled_at is null');
        DB::statement('alter table other_collections add constraint other_collections_amount_positive check (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('other_collections');
        Schema::dropIfExists('collection_types');
    }
};
