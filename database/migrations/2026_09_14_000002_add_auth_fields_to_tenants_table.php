<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'password')) {
                $table->string('password')->nullable();
            }
            if (! Schema::hasColumn('tenants', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('password');
            }
            if (! Schema::hasColumn('tenants', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('phone_verified_at');
            }
            if (! Schema::hasColumn('tenants', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('tenants', 'remember_token')) {
                $table->rememberToken()->after('last_login_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('tenants', 'password') ? 'password' : null,
                Schema::hasColumn('tenants', 'phone_verified_at') ? 'phone_verified_at' : null,
                Schema::hasColumn('tenants', 'email_verified_at') ? 'email_verified_at' : null,
                Schema::hasColumn('tenants', 'last_login_at') ? 'last_login_at' : null,
                Schema::hasColumn('tenants', 'remember_token') ? 'remember_token' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
