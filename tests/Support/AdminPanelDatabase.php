<?php

namespace Tests\Support;

use App\Models\RolesModel;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class AdminPanelDatabase
{
    public static function build(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (glob(database_path('migrations/*.php')) as $path) {
            $file = basename($path);
            // These are historical MySQL conversions, not panel behavior under test.
            if (in_array($file, [
                '2024_12_31_011755_update_personal_access_tokens_for_uuid.php',
                '2025_08_06_203407_update_sessions_user_id_for_ulid.php',
                '2026_01_28_184048_add_design_done_to_advert_submissions_status.php',
            ], true)) {
                continue;
            }
            (require $path)->up();
        }
        Schema::table('advert_submissions', fn ($table) => $table->string('status')->change());
        Schema::enableForeignKeyConstraints();
        (new \Database\Seeders\PerformanceRewardPlanSeeder)->run();
        foreach (['GOLD' => 50, 'PLATINUM' => 30, 'SILVER' => 10] as $code => $price) {
            \App\Models\TokenType::where('code', $code)->update(['unit_price' => $price]);
        }
    }

    public static function user(string $role = 'admin'): User
    {
        $roleRecord = RolesModel::firstOrCreate(['slug' => $role], ['name' => ucfirst($role)]);

        return User::create([
            'fullname' => ucfirst($role).' Member', 'username' => $role.'-'.random_int(1000, 9999),
            'email' => $role.'-'.random_int(1000, 9999).'@example.test',
            'phone' => '+2547'.random_int(10000000, 99999999), 'gender' => 'Male',
            'occupation' => 'Test', 'password' => 'panel-test-password',
            'role_id' => $roleRecord->id, 'is_active' => true, 'my_code' => (string) random_int(1000000000, 9999999999),
        ]);
    }
}
