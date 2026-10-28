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
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                if (! Schema::hasColumn('tenants', 'google_id')) {
                    $table->string('google_id')->nullable()->unique()->after('email');
                }
                if (! Schema::hasColumn('tenants', 'avatar')) {
                    $table->string('avatar', 500)->nullable()->after('google_id');
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'google_id')) {
                    $table->string('google_id')->nullable()->unique()->after('email');
                }
                if (! Schema::hasColumn('users', 'avatar')) {
                    $table->string('avatar', 500)->nullable()->after('google_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table) {
                $columns = array_filter(['google_id', 'avatar'], fn ($col) => Schema::hasColumn('tenants', $col));
                if (! empty($columns)) {
                    $table->dropColumn(array_values($columns));
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = array_filter(['google_id', 'avatar'], fn ($col) => Schema::hasColumn('users', $col));
                if (! empty($columns)) {
                    $table->dropColumn(array_values($columns));
                }
            });
        }
    }
};
