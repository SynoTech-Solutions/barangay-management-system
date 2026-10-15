<?php

use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->string('kind', 16);
            $table->string('group_type', 16)->nullable();
            $table->string('code', 32);
            $table->string('name', 200);
            $table->unsignedSmallInteger('level');
            $table->string('remarks', 255)->nullable();
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['barangay_id', 'code']);
            $table->index(['barangay_id', 'parent_id']);
            $table->index(['barangay_id', 'group_id']);
        });

        $group = AccountKind::Group->value;
        $types = implode("', '", array_map(fn (AccountGroupType $type): string => $type->value, AccountGroupType::cases()));
        $kinds = implode("', '", array_map(fn (AccountKind $kind): string => $kind->value, AccountKind::cases()));

        DB::statement("alter table accounts add constraint accounts_kind_valid check (kind in ('{$kinds}'))");
        DB::statement("alter table accounts add constraint accounts_group_shape check ((kind = '{$group}' and parent_id is null and group_id is null and level = 0 and group_type in ('{$types}')) or (kind <> '{$group}' and parent_id is not null and group_id is not null and level > 0 and group_type is null))");
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
