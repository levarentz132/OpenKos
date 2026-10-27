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
            if (! Schema::hasColumn('tenants', 'bank_name')) {
                $table->string('bank_name', 50)->nullable()->after('id_card_number');
            }
            if (! Schema::hasColumn('tenants', 'bank_account_number')) {
                $table->string('bank_account_number', 50)->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('tenants', 'bank_account_holder')) {
                $table->string('bank_account_holder', 255)->nullable()->after('bank_account_number');
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
                Schema::hasColumn('tenants', 'bank_name') ? 'bank_name' : null,
                Schema::hasColumn('tenants', 'bank_account_number') ? 'bank_account_number' : null,
                Schema::hasColumn('tenants', 'bank_account_holder') ? 'bank_account_holder' : null,
            ]);

            if (! empty($columns)) {
                $table->dropColumn(array_values($columns));
            }
        });
    }
};
