<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_frauds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('reason', 255)->nullable();
            $table->text('details')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('flagged_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'flagged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_frauds');
    }
};
