<?php

use App\Enums\IssuedDocumentStatus;
use App\Enums\ReceiptDocumentType;
use App\Models\OfficialReceipt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string NORMALIZED = "upper(btrim(regexp_replace(d.or_number, '\\s+', ' ', 'g')))";

    public function up(): void
    {
        Schema::create('official_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('or_number', 32);
            $table->string('document_type', 32);
            $table->unsignedBigInteger('document_id');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'document_type', 'document_id']);
            $table->index(['barangay_id', 'or_number']);
        });

        DB::statement('create unique index '.OfficialReceipt::ACTIVE_INDEX.' on official_receipts (barangay_id, or_number) where cancelled_at is null');

        $cancelled = IssuedDocumentStatus::Cancelled->value;
        foreach ([
            ReceiptDocumentType::IdCard->value => ['id_cards', "case when d.status = '{$cancelled}' then coalesce(d.cancelled_at, now()) end"],
            ReceiptDocumentType::BarangayClearance->value => ['barangay_clearances', "case when d.status = '{$cancelled}' then coalesce(d.cancelled_at, now()) end"],
            ReceiptDocumentType::BusinessClearance->value => ['business_clearances', "case when d.status = '{$cancelled}' then coalesce(d.cancelled_at, now()) end"],
            ReceiptDocumentType::OtherCollection->value => ['other_collections', 'd.cancelled_at'],
        ] as $type => [$table, $cancelledAt]) {
            DB::statement(
                'insert into official_receipts (barangay_id, or_number, document_type, document_id, cancelled_at, created_at, updated_at)
                 select d.barangay_id, '.self::NORMALIZED.", ?, d.id, {$cancelledAt}, now(), now()
                 from {$table} d
                 where d.or_number is not null and btrim(d.or_number) <> ''
                 order by d.id
                 on conflict (barangay_id, or_number) where cancelled_at is null do nothing",
                [$type],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('official_receipts');
    }
};
