<?php

use App\Enums\LiquidationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('voucher_id')->constrained()->restrictOnDelete();
            $table->string('liquidation_number', 32);
            $table->date('liquidated_on');
            $table->string('or_number', 32)->nullable();
            $table->date('or_date')->nullable();
            $table->string('status', 16)->default(LiquidationStatus::ForClearing->value);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cleared_at')->nullable();
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('last_printed_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'voucher_id']);
            $table->unique(['barangay_id', 'liquidation_number']);
            $table->index(['barangay_id', 'status']);
        });

        Schema::create('liquidation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('liquidation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_item_id')->constrained()->restrictOnDelete();
            $table->decimal('actual_amount', 12, 2);
            $table->timestamps();

            $table->unique(['barangay_id', 'voucher_item_id']);
            $table->index(['barangay_id', 'liquidation_id']);
        });

        $statuses = implode("', '", array_map(fn (LiquidationStatus $status): string => $status->value, LiquidationStatus::cases()));
        DB::statement("alter table liquidations add constraint liquidations_status_valid check (status in ('{$statuses}'))");
        DB::statement('alter table liquidation_items add constraint liquidation_items_actual_not_negative check (actual_amount >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidation_items');
        Schema::dropIfExists('liquidations');
    }
};
