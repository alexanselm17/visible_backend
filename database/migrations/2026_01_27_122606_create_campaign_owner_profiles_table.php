<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_owner_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // business info
            $table->string('business_name');
            $table->string('business_category')->nullable(); // or category_id if you have a table
            $table->string('mpesa_phone');

            // optional branding/contact
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            // account control
            $table->enum('account_status', ['PENDING', 'ACTIVE', 'SUSPENDED'])
                ->default('PENDING');

            // optional shortcut to active subscription (nullable)
            $table->uuid('current_subscription_id')->nullable();

            $table->timestamps();

            // FK for current subscription (added as separate FK to avoid order issues)
            $table->foreign('current_subscription_id')
                ->references('id')
                ->on('user_subscriptions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_owner_profiles');
    }
};
