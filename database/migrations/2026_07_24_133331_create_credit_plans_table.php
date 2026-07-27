<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->decimal('base_credits', 15, 2);
            $table->decimal('bonus_credits', 15, 2)->default(0);

            // New column: The exact number of people who will post the product
            $table->integer('promoters_reach');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_plans');
    }
};
