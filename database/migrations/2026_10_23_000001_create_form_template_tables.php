<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_picklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'is_active']);
        });
        DB::statement('create unique index form_picklists_name_unique on form_picklists (barangay_id, lower(name))');

        Schema::create('form_picklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_picklist_id')->constrained()->restrictOnDelete();
            $table->string('label', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['barangay_id', 'form_picklist_id', 'is_active']);
        });
        DB::statement('create unique index form_picklist_items_label_unique on form_picklist_items (barangay_id, form_picklist_id, lower(label))');

        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('template_number', 32);
            $table->foreignId('type_id')->constrained('lookup_values')->restrictOnDelete();
            $table->string('name', 150);
            $table->string('kind', 16);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['barangay_id', 'template_number']);
            $table->index(['barangay_id', 'type_id', 'is_active']);
        });
        DB::statement('create unique index form_templates_name_unique on form_templates (barangay_id, lower(name))');

        Schema::create('form_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->text('body')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_file_name', 255)->nullable();
            $table->unsignedInteger('pdf_size_bytes')->nullable();
            $table->char('pdf_sha256', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['barangay_id', 'form_template_id', 'version']);
        });

        Schema::table('form_templates', function (Blueprint $table) {
            $table->foreign('current_version_id')->references('id')->on('form_template_versions')->restrictOnDelete();
        });

        Schema::create('form_template_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_template_version_id')->constrained()->restrictOnDelete();
            $table->string('key', 40);
            $table->string('label', 120);
            $table->string('input_type', 16);
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position');
            $table->foreignId('form_picklist_id')->nullable()->constrained()->restrictOnDelete();

            $table->unique(['barangay_id', 'form_template_version_id', 'key']);
            $table->index(['barangay_id', 'form_template_version_id', 'position']);
        });

        Schema::create('form_pdf_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('form_template_version_id')->constrained()->restrictOnDelete();
            $table->string('pdf_field', 255);
            $table->string('pdf_field_type', 16);
            $table->string('pdf_on_state', 64)->nullable();
            $table->string('token', 80)->nullable();
            $table->string('checked_value', 64)->nullable();
            $table->string('unchecked_value', 64)->nullable();

            $table->unique(['barangay_id', 'form_template_version_id', 'pdf_field']);
        });

        DB::unprepared(<<<'SQL'
            create or replace function form_template_versions_immutable() returns trigger language plpgsql as $$
            begin
                raise exception 'form template versions and their fields cannot change; save a new version' using errcode = 'restrict_violation';
            end;
            $$;

            create trigger form_template_versions_immutable before update or delete on form_template_versions
                for each row execute function form_template_versions_immutable();
            create trigger form_template_fields_immutable before update or delete on form_template_fields
                for each row execute function form_template_versions_immutable();
            create trigger form_pdf_mappings_immutable before update or delete on form_pdf_mappings
                for each row execute function form_template_versions_immutable();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists form_pdf_mappings_immutable on form_pdf_mappings; drop trigger if exists form_template_fields_immutable on form_template_fields; drop trigger if exists form_template_versions_immutable on form_template_versions; drop function if exists form_template_versions_immutable();');
        Schema::dropIfExists('form_pdf_mappings');
        Schema::dropIfExists('form_template_fields');
        Schema::table('form_templates', function (Blueprint $table) {
            $table->dropForeign(['current_version_id']);
        });
        Schema::dropIfExists('form_template_versions');
        Schema::dropIfExists('form_templates');
        Schema::dropIfExists('form_picklist_items');
        Schema::dropIfExists('form_picklists');
    }
};
