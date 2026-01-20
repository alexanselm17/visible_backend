
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fraud_reviews', function (Blueprint $table) {
            $table->id();

            // Change to uuid() if your campaign IDs are UUID.
            $table->unsignedBigInteger('campaign_id');

            // advert_id is UUID in your output
            $table->uuid('advert_id');

            // matching_views_timestamp key e.g. "73_yesterday"
            $table->string('pattern_key');

            // Unique signature for (campaign_id + advert_id + pattern_key)
            $table->string('pattern_hash')->unique();

            // Store the entire fraud group as JSON
            $table->json('fraud_payload');

            // CONFIRMED = Fraud, REJECTED = Not fraud
            $table->enum('status', ['CONFIRMED', 'REJECTED']);

            // Who reviewed (admin id). Nullable if you don’t use auth.
            $table->uuid('reviewed_by')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['campaign_id', 'advert_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_reviews');
    }
};
