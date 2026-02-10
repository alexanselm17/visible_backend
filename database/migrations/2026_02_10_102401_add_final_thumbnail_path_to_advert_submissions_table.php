<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            $table
                ->string('final_thumbnail_path')
                ->nullable()
                ->after('final_video_path');
        });
    }

    public function down(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            $table->dropColumn('final_thumbnail_path');
        });
    }
};
