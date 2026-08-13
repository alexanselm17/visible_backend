<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('media_type', 20)->unique();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->char('currency', 3)->default('KES');
            $table->unsignedInteger('seconds_per_token')->nullable();
            $table->unsignedInteger('max_video_duration_seconds')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('token_wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('token_type_id')->index();
            $table->unsignedBigInteger('balance')->default(0);
            $table->unsignedBigInteger('locked_balance')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'token_type_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('token_type_id')->references('id')->on('token_types')->cascadeOnDelete();
        });

        Schema::create('token_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('token_type_id')->index();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total_amount', 15, 2);
            $table->char('currency', 3)->default('KES');
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('payment_reference')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('token_type_id')->references('id')->on('token_types')->restrictOnDelete();
        });

        Schema::create('token_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('token_wallet_id')->index();
            $table->string('type', 30)->index();
            $table->unsignedBigInteger('amount');
            $table->string('description');
            $table->uuid('reference_id')->nullable()->index();
            $table->string('reference_type')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('token_wallet_id')->references('id')->on('token_wallets')->cascadeOnDelete();
        });

        Schema::create('token_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('token_wallet_id')->index();
            $table->uuid('advert_submission_id')->unique();
            $table->unsignedInteger('amount_locked');
            $table->unsignedInteger('amount_spent')->default(0);
            $table->unsignedInteger('amount_released')->default(0);
            $table->string('status', 30)->default('ACTIVE')->index();
            $table->timestamps();

            $table->foreign('token_wallet_id')->references('id')->on('token_wallets')->cascadeOnDelete();
            $table->foreign('advert_submission_id')->references('id')->on('advert_submissions')->cascadeOnDelete();
        });

        $now = now();

        DB::table('token_types')->insert([
            [
                'id' => (string) Str::uuid(),
                'code' => 'GOLD',
                'name' => 'Gold',
                'media_type' => 'VIDEO',
                'unit_price' => null,
                'currency' => 'KES',
                'seconds_per_token' => 30,
                'max_video_duration_seconds' => null,
                'description' => 'Video advertising token. One Gold token currently covers up to 30 seconds of video.',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'PLATINUM',
                'name' => 'Platinum',
                'media_type' => 'IMAGE',
                'unit_price' => null,
                'currency' => 'KES',
                'seconds_per_token' => null,
                'max_video_duration_seconds' => null,
                'description' => 'Image advertising token. One Platinum token covers one image campaign submission.',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::uuid(),
                'code' => 'SILVER',
                'name' => 'Silver',
                'media_type' => 'TEXT',
                'unit_price' => null,
                'currency' => 'KES',
                'seconds_per_token' => null,
                'max_video_duration_seconds' => null,
                'description' => 'Text advertising token. One Silver token covers one text campaign submission.',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('token_holds');
        Schema::dropIfExists('token_transactions');
        Schema::dropIfExists('token_purchases');
        Schema::dropIfExists('token_wallets');
        Schema::dropIfExists('token_types');
    }
};
