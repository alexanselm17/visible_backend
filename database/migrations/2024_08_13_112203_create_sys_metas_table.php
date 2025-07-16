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
        Schema::create('sys_metas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->integer('meta_index');
            $table->string('meta_key');
            $table->string('meta_value');
            $table->string('meta_shortcode');
            $table->timestamps();

        });
    }


    public function down()
    {
        Schema::dropIfExists('sys_metas');
    }
};
