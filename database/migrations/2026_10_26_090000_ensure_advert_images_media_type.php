<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('advert_images', 'type')) {
            return;
        }

        Schema::table('advert_images', function (Blueprint $table) {
            $table->string('type', 20)->default('IMAGE');
        });

        DB::table('advert_images')->whereNotNull('video_path')->where('video_path', '!=', '')
            ->update(['type' => 'VIDEO']);
    }

    public function down(): void
    {
        // Compatibility repair: preserve media classifications, including columns predating this migration.
    }
};
