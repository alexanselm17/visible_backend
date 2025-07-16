<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Step 1: Add a new UUID column for tokenable_id
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('new_tokenable_id')->nullable(); // Add new column for UUID
        });

        // Step 2: Update the new_tokenable_id with existing tokenable_id values
        // If tokenable_id is in string or integer format, we update the new column
        DB::table('personal_access_tokens')->update([
            'new_tokenable_id' => DB::raw('CONVERT(tokenable_id USING utf8mb4)')
        ]);

        // Step 3: Drop the old tokenable_id column
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('tokenable_id'); // Drop the old column
        });

        // Step 4: Rename the new_tokenable_id column to tokenable_id
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->renameColumn('new_tokenable_id', 'tokenable_id'); // Rename the new column to tokenable_id
        });

        // Step 5: Add the foreign key constraint for tokenable_id
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->foreign('tokenable_id')->references('id')->on('users')->onDelete('cascade'); // Add foreign key for tokenable_id
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Rollback the migration if needed
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            // Drop the foreign key constraint and the tokenable_id column
            $table->dropForeign(['tokenable_id']);
            $table->dropColumn('tokenable_id');
        });

        // Add the old column back (assuming the old column was an integer)
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->bigInteger('tokenable_id')->nullable(); // Recreate the original column as integer
        });
    }
};
