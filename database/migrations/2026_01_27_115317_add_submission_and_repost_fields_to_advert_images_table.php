<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('advert_images', function (Blueprint $table) {
            if (!Schema::hasColumn('advert_images', 'submission_id')) {
                $table->uuid('submission_id')->nullable()->after('id');
                $table->index('submission_id');
            }

            if (!Schema::hasColumn('advert_images', 'repost_of_id')) {
                $table->uuid('repost_of_id')->nullable()->after('submission_id');
                $table->index('repost_of_id');
            }

            // FKs (wrap in try to avoid issues if constraints already exist)
            try {
                $table->foreign('submission_id')
                    ->references('id')
                    ->on('advert_submissions')
                    ->nullOnDelete();
            } catch (\Throwable $e) {
            }

            try {
                $table->foreign('repost_of_id')
                    ->references('id')
                    ->on('advert_images')
                    ->nullOnDelete();
            } catch (\Throwable $e) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('advert_images', function (Blueprint $table) {
            if (Schema::hasColumn('advert_images', 'repost_of_id')) {
                try {
                    $table->dropForeign(['repost_of_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('repost_of_id');
            }

            if (Schema::hasColumn('advert_images', 'submission_id')) {
                try {
                    $table->dropForeign(['submission_id']);
                } catch (\Throwable $e) {
                }
                $table->dropColumn('submission_id');
            }
        });
    }
};
