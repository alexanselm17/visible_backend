<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_frauds', function (Blueprint $table) {
            $table->id();

            $table->uuid('user_id');
            $table->uuid('reported_by')->nullable();

            $table->string('reason', 255)->nullable();
            $table->text('details')->nullable();
            $table->timestamp('flagged_at')->useCurrent();

            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reported_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'flagged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_frauds');
    }
};
