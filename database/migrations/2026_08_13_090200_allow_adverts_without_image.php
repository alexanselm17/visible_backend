<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_images', function (Blueprint $table) {
            $table->string('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('advert_images')->whereNull('image_path')->update(['image_path' => '']);

        Schema::table('advert_images', function (Blueprint $table) {
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
