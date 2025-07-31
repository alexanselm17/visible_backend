<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fullname');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->uuid('county_id')->nullable();
            $table->foreign('county_id')->references('id')->on('counties')->onDelete('set null');
            
            $table->uuid('subcounty_id')->nullable();
            $table->foreign('subcounty_id')->references('id')->on('sub_counties')->onDelete('set null');
            
            $table->string('occupation');
            $table->enum('gender', ['Male', 'Female']);
            $table->boolean('is_logged_in')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('role_id')->nullable();
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null'); // Foreign key constraint
         
            $table->string('referal_code');
            $table->string('my_code')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
