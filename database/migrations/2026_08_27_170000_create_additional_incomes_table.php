<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_incomes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('category')->default('other');
            $table->decimal('amount', 12, 2);
            $table->date('income_date');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('income_date');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_incomes');
    }
};
