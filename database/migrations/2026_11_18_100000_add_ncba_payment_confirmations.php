<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('token_purchases', 'payment_code')) {
            Schema::table('token_purchases', function (Blueprint $table) {
                $table->string('payment_code', 12)->nullable()->unique();
            });
        }

        // Existing pending orders get instructions too; never rewrite prices or paid receipts.
        foreach (DB::table('token_purchases')->where('status', 'PENDING')->whereNull('payment_code')->orderBy('id')->cursor() as $purchase) {
            do {
                $code = 'VD'.strtoupper(bin2hex(random_bytes(5)));
            } while (DB::table('token_purchases')->where('payment_code', $code)->exists());
            DB::table('token_purchases')->where('id', $purchase->id)->whereNull('payment_code')->update(['payment_code' => $code]);
        }

        if (! Schema::hasTable('ncba_payments')) {
            Schema::create('ncba_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('transaction_id', 32)->unique();
                $table->string('bank_reference', 64)->nullable()->unique();
                $table->string('alias', 64);
                $table->string('shortcode', 16);
                $table->unsignedBigInteger('amount_minor');
                $table->char('currency', 3)->default('KES');
                $table->dateTime('transacted_at');
                $table->string('narrative', 255)->nullable();
                $table->string('payment_code', 12)->nullable()->index();
                $table->text('payer_mobile');
                $table->text('payer_name');
                $table->string('fingerprint', 64);
                $table->string('status', 24)->default('REVIEW')->index();
                $table->string('review_reason')->nullable();
                $table->uuid('token_purchase_id')->nullable()->index();
                $table->foreign('token_purchase_id')->references('id')->on('token_purchases')->restrictOnDelete();
                $table->unsignedInteger('delivery_count')->default(1);
                $table->unsignedInteger('conflict_count')->default(0);
                $table->timestamp('last_received_at');
                $table->timestamp('last_conflict_at')->nullable();
                $table->timestamp('applied_at')->nullable();
                $table->uuid('reviewed_by')->nullable();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Preserve bank receipts, order references and financial audit history on rollback.
    }
};
