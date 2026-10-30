<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenants', 'tenant_code')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('tenant_code', 32)->nullable()->unique()->after('user_id');
            });
        }

        // Backfill existing tenants with a unique tenant_code (e.g., TNT-0001)
        $tenants = DB::table('tenants')->whereNull('tenant_code')->orderBy('id')->get(['id']);
        foreach ($tenants as $tenant) {
            DB::table('tenants')
                ->where('id', $tenant->id)
                ->update(['tenant_code' => sprintf('TNT-%04d', $tenant->id)]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'tenant_code')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropUnique(['tenant_code']);
                $table->dropColumn('tenant_code');
            });
        }
    }
};

