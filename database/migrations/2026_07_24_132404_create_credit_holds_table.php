<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('wallet_id')->index();

            $table->uuid('advert_submission_id')->index();

            $table->decimal('amount_locked', 15, 2);
            $table->decimal('amount_spent', 15, 2)->default(0);
            $table->decimal('amount_released', 15, 2)->default(0);

            $table->enum('status', ['ACTIVE', 'SETTLED', 'PARTIALLY_SETTLED', 'CANCELLED'])->default('ACTIVE');

            $table->timestamps();

            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->foreign('advert_submission_id')->references('id')->on('advert_submissions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_holds');
    }
};
