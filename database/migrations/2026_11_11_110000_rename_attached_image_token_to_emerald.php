<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $types = DB::table('token_types')->whereIn('code', ['PLATINUM_ADS', 'EMERALD'])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('code');
            $legacy = $types->get('PLATINUM_ADS');
            $emerald = $types->get('EMERALD');

            if (($legacy && $emerald)
                || ($legacy && $legacy->media_type !== 'ADS_IMAGE')
                || ($emerald && $emerald->media_type !== 'ADS_IMAGE')) {
                throw new RuntimeException('Cannot rename the attached-image token to EMERALD: conflicting token configuration. Resolve the token codes without deleting balances or history, then rerun the migration.');
            }

            if (! $legacy) {
                return;
            }

            // Rename the same row: IDs, pricing, activation and all financial links stay intact.
            $changes = ['code' => 'EMERALD', 'updated_at' => now()];
            if (in_array($legacy->name, ['Platinum Ads', 'Platinum Ads · Attached image'], true)) {
                $changes['name'] = 'Emerald · Attached image';
            }
            DB::table('token_types')->where('id', $legacy->id)->update($changes);
        });
    }

    public function down(): void
    {
        // Keep the public token identity and financial records stable on rollback.
    }
};
