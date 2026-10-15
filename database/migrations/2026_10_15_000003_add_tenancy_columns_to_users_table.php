<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('barangay_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->string('username', 64)->nullable()->after('name');
            $table->string('designation')->nullable()->after('username');
            $table->boolean('is_active')->default(true)->after('designation');
            $table->timestamp('last_login_at')->nullable();
            $table->string('email')->nullable()->change();
        });

        DB::statement("update users set username = lower(split_part(email, '@', 1)) || '-' || id where username is null");

        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable(false)->change();
        });

        DB::statement('create unique index users_username_lower_unique on users (lower(username))');
        DB::statement('create index users_barangay_id_name_index on users (barangay_id, name)');
    }

    public function down(): void
    {
        DB::statement('drop index if exists users_username_lower_unique');
        DB::statement('drop index if exists users_barangay_id_name_index');

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('barangay_id');
            $table->dropColumn(['username', 'designation', 'is_active', 'last_login_at']);
        });
    }
};
