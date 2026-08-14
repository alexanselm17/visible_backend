<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('campaigns', 'owner_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->uuid('owner_id')->nullable()->change();
            });
        } else {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->uuid('owner_id')->nullable();
            });
        }

        // 2. Backfill existing campaigns (set a default owner to jeff)
        DB::table('campaigns')
            ->whereNull('owner_id')
            ->orWhere('owner_id', '')
            ->update([
                'owner_id' => '3acf6e5a-591d-40ea-bf4a-230190bd6795',
            ]);

        // 3. Add foreign key
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // 4. Make owner_id required again
        Schema::table('campaigns', function (Blueprint $table) {
            $table->uuid('owner_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->uuid('owner_id')->nullable()->change();
        });
    }
};
