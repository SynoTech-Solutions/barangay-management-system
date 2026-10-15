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
        Schema::create('form_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('transaction_number', 32);
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_template_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default(IssuedDocumentStatus::Issued->value);
            $table->string('or_number', 32)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->jsonb('snapshot');
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

            $table->unique(['barangay_id', 'transaction_number']);
            $table->index(['barangay_id', 'status', 'issued_at']);
            $table->index(['barangay_id', 'resident_id']);
            $table->index(['barangay_id', 'form_template_id', 'issued_at']);
        });

        DB::statement("create unique index form_transactions_or_number_unique on form_transactions (barangay_id, or_number) where or_number is not null and status <> '".IssuedDocumentStatus::Cancelled->value."'");
        DB::statement('alter table form_transactions add constraint form_transactions_amount_not_negative check (amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('form_transactions');
    }
};
