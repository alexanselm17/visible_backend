<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ncba_payments', 'verified_payload')) {
            Schema::table('ncba_payments', function (Blueprint $table) {
                $table->text('verified_payload')->nullable();
            });
        }
        // A durable mutex also serializes claims arriving before the bank receipt exists.
        if (! Schema::hasTable('ncba_receipt_keys')) {
            Schema::create('ncba_receipt_keys', function (Blueprint $table) {
                $table->string('receipt_number', 32)->primary();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('ncba_receipt_claims')) {
            Schema::create('ncba_receipt_claims', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('token_purchase_id')->constrained('token_purchases')->restrictOnDelete();
                $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
                $table->string('receipt_number', 32)->index();
                $table->unsignedInteger('sequence');
                $table->unique(['token_purchase_id', 'sequence']);
                $table->foreignUuid('ncba_payment_id')->nullable()->constrained('ncba_payments')->restrictOnDelete();
                $table->string('status', 24)->index();
                $table->string('reason_code', 64)->nullable();
                $table->text('owner_phone_snapshot')->nullable();
                $table->dateTime('phone_verified_at_snapshot')->nullable();
                $table->text('purchase_snapshot');
                $table->unsignedInteger('submission_count')->default(1);
                $table->dateTime('submitted_at');
                $table->dateTime('last_submitted_at');
                $table->dateTime('decided_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('ncba_payment_events')) {
            Schema::create('ncba_payment_events', function (Blueprint $table) {
                $table->bigIncrements('sequence');
                $table->uuid('id')->unique();
                $table->string('event_type', 64)->index();
                $table->string('receipt_number', 32)->nullable()->index();
                $table->foreignUuid('ncba_payment_id')->nullable()->constrained('ncba_payments')->restrictOnDelete();
                $table->foreignUuid('receipt_claim_id')->nullable()->constrained('ncba_receipt_claims')->restrictOnDelete();
                $table->foreignUuid('token_purchase_id')->nullable()->constrained('token_purchases')->restrictOnDelete();
                // Retain the original UUID even if the staff account is later removed.
                $table->uuid('actor_id')->nullable()->index();
                $table->string('actor_type', 16);
                $table->json('details');
                $table->text('evidence')->nullable();
                $table->dateTime('created_at')->index();
            });
        }
    }

    public function down(): void
    {
        // Financial claims and their audit trail must survive a deployment rollback.
    }
};
