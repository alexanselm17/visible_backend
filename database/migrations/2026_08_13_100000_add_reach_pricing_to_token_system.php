<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_types', function (Blueprint $table) {
            $table->unsignedInteger('people_per_token')
                ->default(10)
                ->after('seconds_per_token');
        });

        Schema::table('advert_submissions', function (Blueprint $table) {
            $table->unsignedInteger('target_reach')
                ->nullable()
                ->after('target_audience');
            $table->unsignedInteger('media_units')
                ->default(1)
                ->after('tokens_spent');
            $table->unsignedInteger('reach_units')
                ->default(1)
                ->after('media_units');
            $table->unsignedInteger('people_per_token_snapshot')
                ->nullable()
                ->after('reach_units');
            $table->unsignedInteger('seconds_per_token_snapshot')
                ->nullable()
                ->after('people_per_token_snapshot');
        });

        DB::table('token_types')->update([
            'people_per_token' => 10,
        ]);

        DB::table('token_types')
            ->where('code', 'GOLD')
            ->update([
                'description' => 'Video advertising token. Usage depends on video length and target reach.',
            ]);

        DB::table('token_types')
            ->where('code', 'PLATINUM')
            ->update([
                'description' => 'Image advertising token. Usage depends on the target reach.',
            ]);

        DB::table('token_types')
            ->where('code', 'SILVER')
            ->update([
                'description' => 'Text advertising token. Usage depends on the target reach.',
            ]);
    }

    public function down(): void
    {
        Schema::table('advert_submissions', function (Blueprint $table) {
            $table->dropColumn([
                'target_reach',
                'media_units',
                'reach_units',
                'people_per_token_snapshot',
                'seconds_per_token_snapshot',
            ]);
        });

        Schema::table('token_types', function (Blueprint $table) {
            $table->dropColumn('people_per_token');
        });
    }
};
