<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sessions', function (Blueprint $table) {
            // Drop the existing user_id column
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            // Add the new user_id column with correct type
            // For ULIDs/UUIDs, use string or char(26) for ULID, char(36) for UUID
            $table->string('user_id', 26)->nullable()->after('id'); // For ULID
            // OR for UUID:
            // $table->uuid('user_id')->nullable()->after('id');

            // Add index for performance
            $table->index(['user_id']);
        });
    }

    public function down()
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });

        Schema::table('sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->index(['user_id']);
        });
    }
};
