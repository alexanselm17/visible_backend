<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advert_qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('advert_id');
            $table->string('identifier_snapshot', 10);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('generated_at');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('advert_id')->references('id')->on('advert_images')->cascadeOnDelete();
            $table->unique(['user_id', 'advert_id'], 'advert_qr_user_advert_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_qr_codes');
    }
};
