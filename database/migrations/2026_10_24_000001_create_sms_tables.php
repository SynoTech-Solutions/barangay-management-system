<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('title', 50);
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'title']);
        });
        DB::statement('create unique index sms_templates_title_unique on sms_templates (barangay_id, lower(title))');

        Schema::create('sms_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'is_active', 'name']);
        });
        DB::statement('create unique index sms_groups_name_unique on sms_groups (barangay_id, lower(name))');

        Schema::create('sms_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('sms_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('mobile_number', 16)->nullable();
            $table->string('label', 100)->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'sms_group_id']);
            $table->index(['barangay_id', 'resident_id']);
        });
        DB::statement('alter table sms_group_members add constraint sms_group_members_one_target check ((resident_id is null) <> (mobile_number is null))');
        DB::statement('create unique index sms_group_members_resident_unique on sms_group_members (sms_group_id, resident_id) where resident_id is not null');
        DB::statement('create unique index sms_group_members_number_unique on sms_group_members (sms_group_id, mobile_number) where mobile_number is not null');

        Schema::create('sms_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->string('source', 20);
            $table->foreignId('sms_group_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('recipient_count');
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedSmallInteger('segments');
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['barangay_id', 'created_at']);
        });

        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->constrained()->restrictOnDelete();
            $table->foreignId('sms_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('resident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient', 16)->nullable();
            $table->text('body')->nullable();
            $table->string('encoding', 8);
            $table->unsignedSmallInteger('segments');
            $table->string('status', 16);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code', 64)->nullable();
            $table->string('provider_message_id', 128)->nullable();
            $table->foreignId('queued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('queued_at');
            $table->timestamp('sending_started_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pruned_at')->nullable();
            $table->timestamps();

            $table->index(['barangay_id', 'status', 'queued_at']);
            $table->index(['barangay_id', 'queued_at']);
            $table->index(['barangay_id', 'sms_batch_id']);
            $table->index(['barangay_id', 'resident_id']);
        });
        DB::statement("alter table sms_messages add constraint sms_messages_status_check check (status in ('queued', 'sending', 'sent', 'failed', 'cancelled'))");
        DB::statement("create index sms_messages_sending_index on sms_messages (barangay_id, sending_started_at) where status = 'sending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_batches');
        Schema::dropIfExists('sms_group_members');
        Schema::dropIfExists('sms_groups');
        Schema::dropIfExists('sms_templates');
    }
};
