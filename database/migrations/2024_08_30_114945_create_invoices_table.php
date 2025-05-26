<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->string('type')->comment('Repayment/Reconciliation/Invoice Sales');
            $table->decimal('amount', 15, 2);
           
            $table->uuid('processed_by')->nullable();
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null'); // Foreign key constraint for users

            $table->decimal('customer_balance', 15, 2);
            $table->string('invoice_note')->nullable();
            

            $table->uuid('posted_by')->nullable(); // Foreign key for users (posted_by)
            $table->foreign('posted_by')->references('id')->on('users')->onDelete('set null'); // Foreign key constraint for users

            $table->uuid('banking')->nullable(); // Foreign key for bankings (UUID)
            $table->foreign('banking')->references('id')->on('bankings')->onDelete('set null'); // Foreign key constraint for bankings

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
