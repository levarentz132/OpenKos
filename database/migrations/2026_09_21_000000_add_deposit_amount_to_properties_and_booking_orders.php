<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('properties') && ! Schema::hasColumn('properties', 'deposit_amount')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->decimal('deposit_amount', 12, 2)->default(500000.00)->after('description');
            });
        }

        if (Schema::hasTable('booking_orders')) {
            Schema::table('booking_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('booking_orders', 'deposit_amount')) {
                    $table->decimal('deposit_amount', 12, 2)->default(0)->after('amount');
                }
                if (! Schema::hasColumn('booking_orders', 'rent_amount')) {
                    $table->decimal('rent_amount', 12, 2)->default(0)->after('deposit_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'deposit_amount')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn('deposit_amount');
            });
        }

        if (Schema::hasTable('booking_orders')) {
            Schema::table('booking_orders', function (Blueprint $table) {
                $table->dropColumn(['deposit_amount', 'rent_amount']);
            });
        }
    }
};
