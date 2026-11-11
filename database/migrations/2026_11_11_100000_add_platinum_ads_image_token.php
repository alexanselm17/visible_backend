<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Some older installations used ENUM('image', 'ads') instead of VARCHAR.
        // Widen only restricted columns, preserving every stored value and its default.
        foreach (['advert_submissions', 'advert_images'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            $column = collect(Schema::getColumns($tableName))->firstWhere('name', 'type');
            if ($column && in_array($column['type_name'], ['enum', 'set'], true)) {
                Schema::table($tableName, function (Blueprint $table) use ($column) {
                    $definition = $table->string('type', 20)->nullable($column['nullable'])->change();
                    if ($column['default'] !== null) {
                        $definition->default($column['default']);
                    }
                });
            }
        }

        // Keep the existing token ID, prices, balances, holds and purchase history intact.
        DB::table('token_types')->where('code', 'PLATINUM')->where('name', 'Platinum')
            ->update(['name' => 'Platinum · Full image']);

        DB::table('token_types')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'code' => 'PLATINUM_ADS',
            'name' => 'Platinum Ads · Attached image',
            'media_type' => 'ADS_IMAGE',
            // Deliberately unpriced: an admin must choose the rate, never invent a selling price.
            'unit_price' => null,
            'currency' => DB::table('token_types')->where('code', 'PLATINUM')->value('currency') ?: 'KES',
            'people_per_token' => 10,
            'seconds_per_token' => null,
            'max_video_duration_seconds' => null,
            'description' => 'For an advert attached to a member’s own image. Separate from standalone full-image Platinum tokens. Usage is 3 submission tokens plus reach tokens.',
            'is_active' => false,
            'sort_order' => 4,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Never remove a token type that may now own purchased balances or financial history.
    }
};
