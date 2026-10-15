<?php

use App\Enums\IssuedDocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cedulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('cedula_number', 32);
            $table->string('form_number', 32)->nullable();
            $table->unsignedSmallInteger('tax_year');
            $table->string('status', 16)->default(IssuedDocumentStatus::Issued->value);
            $table->string('place_of_issue', 255);
            $table->boolean('is_exempt')->default(false);
            $table->decimal('business_gross_receipts', 12, 2)->default(0);
            $table->decimal('salaries', 12, 2)->default(0);
            $table->decimal('real_property_income', 12, 2)->default(0);
            $table->decimal('basic_tax', 12, 2);
            $table->decimal('business_tax', 12, 2);
            $table->decimal('salary_tax', 12, 2);
            $table->decimal('real_property_tax', 12, 2);
            $table->decimal('additional_tax', 12, 2);
            $table->decimal('interest', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->jsonb('breakdown');
            $table->string('verified_by_name')->nullable();
            $table->string('verified_by_title')->nullable();
            $table->string('certified_by_name')->nullable();
            $table->string('certified_by_title')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'cedula_number']);
            $table->index(['barangay_id', 'status', 'issued_at']);
            $table->index(['barangay_id', 'resident_id', 'tax_year']);
        });

        DB::statement("create unique index cedulas_form_number_unique on cedulas (barangay_id, form_number) where form_number is not null and status <> '".IssuedDocumentStatus::Cancelled->value."'");
        DB::statement('alter table cedulas add constraint cedulas_amounts_not_negative check (business_gross_receipts >= 0 and salaries >= 0 and real_property_income >= 0 and amount >= 0)');

        Schema::create('cedula_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->boolean('is_exempt')->default(false);
            $table->decimal('business_gross_receipts', 12, 2)->default(0);
            $table->decimal('salaries', 12, 2)->default(0);
            $table->decimal('real_property_income', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'is_active']);
        });

        DB::statement('create unique index cedula_templates_name_unique on cedula_templates (barangay_id, lower(name))');
        DB::statement('alter table cedula_templates add constraint cedula_templates_amounts_not_negative check (business_gross_receipts >= 0 and salaries >= 0 and real_property_income >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cedula_templates');
        Schema::dropIfExists('cedulas');
    }
};
