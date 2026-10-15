<?php

use App\Enums\BudgetStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('title', 100);
            $table->string('status', 16)->default(BudgetStatus::Draft->value);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'fiscal_year']);
        });

        Schema::create('budget_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'budget_id', 'number']);
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('budget_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();

            $table->unique(['barangay_id', 'budget_version_id', 'account_id']);
            $table->index(['barangay_id', 'account_id']);
        });

        DB::statement('alter table budget_lines add constraint budget_lines_amount_not_negative check (amount >= 0)');

        DB::unprepared(<<<'SQL'
            create or replace function budget_versions_immutable() returns trigger language plpgsql as $$
            begin
                if old.approved_at is not null then
                    raise exception 'budget version % is approved and cannot change', old.id using errcode = 'restrict_violation';
                end if;
                return coalesce(new, old);
            end
            $$;

            create trigger budget_versions_immutable before update or delete on budget_versions
                for each row execute function budget_versions_immutable();

            create or replace function budget_lines_immutable() returns trigger language plpgsql as $$
            begin
                if tg_op in ('UPDATE', 'DELETE') and exists (select 1 from budget_versions where id = old.budget_version_id and approved_at is not null) then
                    raise exception 'budget version % is approved and its lines cannot change', old.budget_version_id using errcode = 'restrict_violation';
                end if;
                if tg_op in ('INSERT', 'UPDATE') and exists (select 1 from budget_versions where id = new.budget_version_id and approved_at is not null) then
                    raise exception 'budget version % is approved and its lines cannot change', new.budget_version_id using errcode = 'restrict_violation';
                end if;
                return coalesce(new, old);
            end
            $$;

            create trigger budget_lines_immutable before insert or update or delete on budget_lines
                for each row execute function budget_lines_immutable();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budget_versions');
        Schema::dropIfExists('budgets');
        DB::unprepared('drop function if exists budget_lines_immutable(); drop function if exists budget_versions_immutable();');
    }
};
