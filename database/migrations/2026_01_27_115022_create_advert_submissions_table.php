<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('advert_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('campaign_id');
            $table->uuid('submitted_by'); // campaign owner user_id

            // owner input
            $table->decimal('capital_invested', 15, 2);
            $table->string('name');
            $table->text('description');
            $table->json('target_audience')->nullable();

            // files
            $table->string('original_image_path');
            $table->string('original_video_path')->nullable();

            // design output
            $table->string('final_image_path')->nullable();
            $table->string('final_video_path')->nullable();

            // workflow
            $table->enum('status', ['PENDING_DESIGN', 'PENDING_APPROVAL', 'REJECTED', 'PUBLISHED'])
                ->default('PENDING_DESIGN');

            $table->uuid('designed_by')->nullable();
            $table->dateTime('designed_at')->nullable();

            $table->uuid('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onDelete('cascade');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('designed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advert_submissions');
    }
};
