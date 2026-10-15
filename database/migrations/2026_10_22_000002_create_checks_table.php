<?php

use App\Enums\CheckStatus;
use App\Models\Check;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('payee_name', 255);
            $table->string('check_number', 32);
            $table->date('check_date');
            $table->decimal('amount', 12, 2);
            $table->string('remarks', 255)->nullable();
            $table->string('status', 16)->default(CheckStatus::ForRelease->value);
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('received_by_name', 150)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'status']);
            $table->index(['barangay_id', 'voucher_id']);
        });

        DB::statement('create unique index '.Check::NUMBER_INDEX.' on checks (barangay_id, bank_account_id, check_number) where cancelled_at is null');
        DB::statement('create unique index '.Check::VOUCHER_INDEX.' on checks (barangay_id, voucher_id) where cancelled_at is null and voucher_id is not null');
        DB::statement('alter table checks add constraint checks_amount_minimum check (amount >= 1)');
        $statuses = implode("', '", array_map(fn (CheckStatus $status): string => $status->value, CheckStatus::cases()));
        DB::statement("alter table checks add constraint checks_status_valid check (status in ('{$statuses}'))");
        DB::statement("alter table checks add constraint checks_cancel_consistent check ((status = '".CheckStatus::Cancelled->value."') = (cancelled_at is not null))");
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
