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
        Schema::create('bankings', function (Blueprint $table) {
            $table->uuid('id')->primary(); // Use UUID as primary key
            $table->string('reference')->nullable()->unique();
            $table->decimal('amount', 15, 2);
            $table->string('name')->nullable();
            $table->string('phone')->nullable();

            // Use UUID for foreign keys
            $table->uuid('processed_by')->nullable(); // Foreign key for users (processed_by)
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null'); // Foreign key constraint for users

            $table->boolean('approval_status')->default(false);
            $table->uuid('approved_by')->nullable(); // Foreign key for users (approved_by)
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null'); // Foreign key constraint for users


            $table->uuid('deposit_method'); // Foreign key for sys_metas (UUID)
            $table->foreign('deposit_method')->references('id')->on('sys_metas')->onDelete('cascade'); // Foreign key constraint for sys_metas


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
        Schema::dropIfExists('bankings');
    }
};
