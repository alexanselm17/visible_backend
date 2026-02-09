<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('advert_submissions', 'original_image_path')) {
                $table->dropColumn('original_image_path');
            }

            if (Schema::hasColumn('advert_submissions', 'original_video_path')) {
                $table->dropColumn('original_video_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            $table->string('original_image_path')->nullable();
            $table->string('original_video_path')->nullable();
        });
    }
};
