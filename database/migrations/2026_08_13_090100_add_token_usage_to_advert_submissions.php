<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            $table->uuid('token_type_id')->nullable()->after('type')->index();
            $table->unsignedInteger('tokens_reserved')->default(0)->after('token_type_id');
            $table->unsignedInteger('tokens_spent')->default(0)->after('tokens_reserved');
            $table->unsignedInteger('video_duration_seconds')->nullable()->after('tokens_spent');
            $table->unsignedInteger('final_video_duration_seconds')->nullable()->after('video_duration_seconds');
            $table->timestamp('tokens_refunded_at')->nullable()->after('final_video_duration_seconds');

            $table->foreign('token_type_id')->references('id')->on('token_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            $table->dropForeign(['token_type_id']);
            $table->dropColumn([
                'token_type_id',
                'tokens_reserved',
                'tokens_spent',
                'video_duration_seconds',
                'final_video_duration_seconds',
                'tokens_refunded_at',
            ]);
        });
    }
};
