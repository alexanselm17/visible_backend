<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('fraud_reviews', function (Blueprint $table) {
            // drop index first if needed
            $table->dropIndex(['campaign_id', 'advert_id']);
        });

        // modify column type using raw SQL (MySQL)
        Schema::table('fraud_reviews', function (Blueprint $table) {
            // change campaign_id from BIGINT to CHAR(36)
            $table->char('campaign_id', 36)->change();
        });

        Schema::table('fraud_reviews', function (Blueprint $table) {
            // re-add the index
            $table->index(['campaign_id', 'advert_id']);
        });
    }

    public function down(): void
    {
        Schema::table('fraud_reviews', function (Blueprint $table) {
            $table->dropIndex(['campaign_id', 'advert_id']);
        });

        Schema::table('fraud_reviews', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->change();
        });

        Schema::table('fraud_reviews', function (Blueprint $table) {
            $table->index(['campaign_id', 'advert_id']);
        });
    }
};
