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
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('card_number', 32);
            $table->string('status', 16)->default(IssuedDocumentStatus::Issued->value);
            $table->string('ctc_number', 32)->nullable();
            $table->string('ctc_place', 255)->nullable();
            $table->date('ctc_date')->nullable();
            $table->string('or_number', 32)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
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

            $table->unique(['barangay_id', 'card_number']);
            $table->index(['barangay_id', 'status', 'issued_at']);
            $table->index(['barangay_id', 'resident_id']);
        });

        DB::statement("create unique index id_cards_or_number_unique on id_cards (barangay_id, or_number) where or_number is not null and status <> '".IssuedDocumentStatus::Cancelled->value."'");
    }

    public function down(): void
    {
        Schema::dropIfExists('id_cards');
    }
};
