<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('advert_images', 'reward')) {
            return;
        }

        Schema::table('advert_images', function (Blueprint $table) {
            $table->dropColumn('reward');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('advert_images', 'reward')) {
            return;
        }

        Schema::table('advert_images', function (Blueprint $table) {
            $table->decimal('reward', 10, 2)->default(0);
        });
    }
};
