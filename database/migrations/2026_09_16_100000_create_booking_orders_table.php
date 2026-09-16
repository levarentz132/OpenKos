<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_orders', function (Blueprint $table) {
            $table->id();
            $table->string('cart_token', 100)->index();
            $table->string('reference', 100)->unique();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name');
            $table->string('guest_phone', 30);
            $table->string('guest_email')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->unsignedInteger('duration_months')->default(1);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('IDR');
            $table->string('status', 32)->default('pending'); // pending, paid, expired, cancelled
            $table->foreignId('lease_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->text('doku_checkout_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['cart_token', 'status']);
            $table->index(['guest_phone', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_orders');
    }
};
