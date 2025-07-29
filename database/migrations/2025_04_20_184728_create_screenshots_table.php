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
        Schema::create('screenshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('screenshot');
            $table->string('timestamp');
            $table->integer('views');
            $table->uuid('advert_id')->nullable(); // Foreign key for users (processed_by)
            $table->foreign('advert_id')->references('id')->on('advert_images')->onDelete('set null'); // Foreign key constraint for users

            $table->uuid('processed_by')->nullable(); // Foreign key for users (processed_by)
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null'); // Foreign key constraint for users

            $table->integer('number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screenshots');
    }
};
