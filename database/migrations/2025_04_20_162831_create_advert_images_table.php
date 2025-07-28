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
        Schema::create('advert_images', function (Blueprint $table) {
            $table->uuid('id')->primary();


            $table->decimal('capital_invested', 15, 2); // up to 999 trillion
            $table->dateTime('valid_until');
            $table->decimal('reward', 10, 2);
            $table->integer('capacity');

            $table->string('image_path');
            $table->string('category');
            $table->string('description');
           
            $table->double('selling_price');
            $table->string('name');
            $table->json('badge')->nullable();
            $table->string('video_path')->nullable();
            $table->uuid('campaign_id');
            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');

            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advert_images');
    }
};
