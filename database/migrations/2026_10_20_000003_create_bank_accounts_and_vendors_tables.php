<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('account_number', 64);
            $table->string('account_name', 150);
            $table->string('depository_bank', 150);
            $table->string('branch_address', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['barangay_id', 'account_number']);
            $table->index(['barangay_id', 'is_active']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('address', 255);
            $table->string('contact_number', 50);
            $table->string('email', 150)->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('tin', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'is_active']);
        });

        DB::statement('create unique index vendors_name_unique on vendors (barangay_id, lower(name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('bank_accounts');
    }
};
