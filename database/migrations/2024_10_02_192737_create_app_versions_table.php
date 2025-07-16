<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str; // Import Str facade for UUID generation

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();// Use UUID as primary key
            $table->string('versions')->default("1.0.0");
            $table->timestamps();
        });

        // Insert a default record with UUID as the id
        DB::table('app_versions')->insert([
            'versions' => '1.0.0',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('app_versions');
    }
};
