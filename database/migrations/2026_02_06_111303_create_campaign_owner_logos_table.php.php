<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_owner_logos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id'); // campaign_owner_profiles.id
            $table->string('logo_path');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('profile_id')
                ->references('id')
                ->on('campaign_owner_profiles')
                ->onDelete('cascade');

            $table->index(['profile_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_owner_logos');
    }
};
