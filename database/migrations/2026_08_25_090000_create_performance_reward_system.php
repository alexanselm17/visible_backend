<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->unsignedInteger('version');
            $table->string('calculation_method')->default('multiply');
            $table->unsignedBigInteger('monthly_maximum_minor')->default(500000);
            $table->char('currency', 3)->default('KES');
            $table->boolean('is_active')->default(false);
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['name', 'version']);
            $table->index(['is_active', 'effective_from', 'effective_until'], 'reward_plans_active_dates_idx');
        });

        Schema::create('reward_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('evaluator_key');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('reward_plan_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_plan_id');
            $table->uuid('reward_metric_id');
            $table->unsignedSmallInteger('weight_basis_points')->default(10000);
            $table->unsignedSmallInteger('minimum_basis_points')->default(0);
            $table->unsignedSmallInteger('maximum_basis_points')->default(10000);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('reward_plan_id')->references('id')->on('reward_plans')->cascadeOnDelete();
            $table->foreign('reward_metric_id')->references('id')->on('reward_metrics')->restrictOnDelete();
            $table->unique(['reward_plan_id', 'reward_metric_id'], 'reward_plan_metric_unique');
        });

        Schema::create('reward_metric_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_plan_metric_id');
            $table->decimal('minimum_value', 18, 4);
            $table->decimal('maximum_value', 18, 4)->nullable();
            $table->unsignedSmallInteger('multiplier_basis_points');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('reward_plan_metric_id')->references('id')->on('reward_plan_metrics')->cascadeOnDelete();
            $table->index(['reward_plan_metric_id', 'minimum_value'], 'reward_metric_tier_lookup_idx');
        });

        Schema::create('reward_payout_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('frequency');
            $table->dateTime('effective_from');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'effective_from'], 'reward_preference_effective_unique');
            $table->index(['user_id', 'effective_from'], 'reward_preference_lookup_idx');
        });

        Schema::create('reward_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('reward_plan_id');
            $table->string('frequency');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedBigInteger('maximum_amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->string('calculation_method')->default('multiply');
            $table->string('status')->default('open');
            $table->unsignedSmallInteger('combined_multiplier_basis_points')->default(0);
            $table->unsignedBigInteger('calculated_amount_minor')->default(0);
            $table->json('calculation_snapshot')->nullable();
            $table->dateTime('calculated_at')->nullable();
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reward_plan_id')->references('id')->on('reward_plans')->restrictOnDelete();
            $table->unique(['user_id', 'starts_at', 'ends_at'], 'reward_user_period_unique');
            $table->index(['status', 'ends_at'], 'reward_period_close_idx');
        });

        Schema::create('reward_period_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_period_id');
            $table->uuid('reward_metric_id');
            $table->decimal('measured_value', 18, 4)->default(0);
            $table->unsignedSmallInteger('multiplier_basis_points')->default(0);
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->foreign('reward_period_id')->references('id')->on('reward_periods')->cascadeOnDelete();
            $table->foreign('reward_metric_id')->references('id')->on('reward_metrics')->restrictOnDelete();
            $table->unique(['reward_period_id', 'reward_metric_id'], 'reward_period_metric_unique');
        });

        Schema::create('reward_payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reward_period_id')->unique();
            $table->uuid('user_id');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->string('status')->default('pending');
            $table->string('provider')->nullable();
            $table->string('payment_reference')->nullable()->unique();
            $table->uuid('idempotency_key')->unique();
            $table->text('failure_reason')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->dateTime('processing_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('reward_period_id')->references('id')->on('reward_periods')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at'], 'reward_payout_status_idx');
        });

        Schema::create('reward_ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('reward_period_id')->nullable();
            $table->uuid('reward_payout_id')->nullable();
            $table->string('type');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3)->default('KES');
            $table->string('idempotency_key')->unique();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reward_period_id')->references('id')->on('reward_periods')->nullOnDelete();
            $table->foreign('reward_payout_id')->references('id')->on('reward_payouts')->nullOnDelete();
            $table->index(['user_id', 'created_at'], 'reward_ledger_user_idx');
        });

        Schema::create('reward_referral_qualifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referrer_user_id');
            $table->uuid('referred_user_id')->unique();
            $table->uuid('qualifying_advert_id')->nullable();
            $table->dateTime('qualified_at');
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->foreign('referrer_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('referred_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('qualifying_advert_id')->references('id')->on('advert_images')->nullOnDelete();
            $table->index(['referrer_user_id', 'qualified_at'], 'reward_referral_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_referral_qualifications');
        Schema::dropIfExists('reward_ledger_entries');
        Schema::dropIfExists('reward_payouts');
        Schema::dropIfExists('reward_period_metrics');
        Schema::dropIfExists('reward_periods');
        Schema::dropIfExists('reward_payout_preferences');
        Schema::dropIfExists('reward_metric_tiers');
        Schema::dropIfExists('reward_plan_metrics');
        Schema::dropIfExists('reward_metrics');
        Schema::dropIfExists('reward_plans');
    }
};
