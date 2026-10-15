<?php

use App\Enums\PaymentType;
use App\Enums\VoucherStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('voucher_number', 32);
            $table->foreignId('budget_id')->constrained()->restrictOnDelete();
            $table->string('payment_type', 16);
            $table->string('status', 16)->default(VoucherStatus::Prepared->value);
            $table->date('voucher_date');
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payee_name', 255);
            $table->string('payee_address', 255)->nullable();
            $table->string('payee_tin', 32)->nullable();
            $table->string('particulars', 500);
            $table->string('responsibility_center', 150)->nullable();
            $table->string('office_unit', 150)->nullable();
            $table->string('treasurer_name')->nullable();
            $table->string('bookkeeper_name')->nullable();
            $table->string('punong_barangay_name')->nullable();
            $table->string('head_of_appropriations_name')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'voucher_number']);
            $table->index(['barangay_id', 'status']);
            $table->index(['barangay_id', 'budget_id']);
            $table->index(['barangay_id', 'voucher_date']);
        });

        $types = implode("', '", array_map(fn (PaymentType $type): string => $type->value, PaymentType::cases()));
        DB::statement("alter table vouchers add constraint vouchers_payment_type_valid check (payment_type in ('{$types}'))");
        $statuses = implode("', '", array_map(fn (VoucherStatus $status): string => $status->value, VoucherStatus::cases()));
        DB::statement("alter table vouchers add constraint vouchers_status_valid check (status in ('{$statuses}'))");

        Schema::create('voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['barangay_id', 'voucher_id']);
            $table->index(['barangay_id', 'account_id']);
        });

        DB::statement('alter table voucher_items add constraint voucher_items_amount_positive check (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_items');
        Schema::dropIfExists('vouchers');
    }
};
