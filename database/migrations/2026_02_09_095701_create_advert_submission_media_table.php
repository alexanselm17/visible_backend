<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('advert_submission_media', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('submission_id');
            $table->enum('type', ['IMAGE', 'VIDEO']);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->foreign('submission_id')
                ->references('id')->on('advert_submissions')
                ->onDelete('cascade');

            $table->index(['submission_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_submission_media');
    }
};
