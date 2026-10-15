<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const array DOCUMENT_TABLES = [
        'residents',
        'id_cards',
        'barangay_clearances',
        'indigency_certificates',
        'businesses',
        'business_clearances',
        'cedulas',
        'blotters',
        'other_collections',
        'vouchers',
        'liquidations',
        'form_templates',
        'form_transactions',
    ];

    public function up(): void
    {
        foreach (self::DOCUMENT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('source_number', 64)->nullable();
                $table->string('import_note', 255)->nullable();
            });
        }

        Schema::table('family_members', function (Blueprint $table) {
            $table->unsignedSmallInteger('birth_year')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn('birth_year');
        });

        foreach (self::DOCUMENT_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['source_number', 'import_note']);
            });
        }
    }
};
