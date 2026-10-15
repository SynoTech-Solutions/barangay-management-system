<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name')->nullable();
            $table->string('scope');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        DB::statement('create unique index roles_barangay_key_unique on roles (barangay_id, key) nulls not distinct');

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 64);
            $table->primary(['role_id', 'permission']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barangay_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        DB::statement('create unique index user_roles_user_role_barangay_unique on user_roles (user_id, role_id, barangay_id) nulls not distinct');
        DB::statement('create index user_roles_user_barangay_index on user_roles (user_id, barangay_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
