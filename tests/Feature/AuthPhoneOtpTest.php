<?php

namespace Tests\Feature;

use App\Models\PhoneVerificationOtp;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class AuthPhoneOtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        config()->set('services.talksasa.api_key', 'test-talksasa-key');
        config()->set('services.talksasa.base_url', 'https://bulksms.talksasa.com/api/v3');
        config()->set('services.talksasa.sender_id', 'VisibleDM');
        config()->set('services.talksasa.otp_resend_cooldown_seconds', 60);
        config()->set('services.talksasa.otp_ttl_minutes', 10);

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fullname');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('phone_verified_at')->nullable();
            $table->uuid('county_id')->nullable();
            $table->uuid('subcounty_id')->nullable();
            $table->string('occupation')->nullable();
            $table->string('gender')->nullable();
            $table->boolean('is_logged_in')->default(false);
            $table->boolean('is_active')->default(false);
            $table->uuid('role_id')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('referal_code')->nullable();
            $table->string('my_code')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        $migration = require database_path('migrations/2026_09_08_090000_add_phone_otp_verification.php');
        $migration->up();

        DB::table('roles')->insert([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Customer Champion',
            'slug' => 'salesman',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_signup_sends_phone_otp_with_talksasa(): void
    {
        Http::fake([
            'bulksms.talksasa.com/*' => Http::response(['message_id' => 'sms-123'], 200),
        ]);

        $response = $this->postJson('/api/v1/auth/signup', $this->signupPayload());

        $response
            ->assertOk()
            ->assertJsonPath('data.requires_phone_verification', true)
            ->assertJsonPath('data.phone', '+254712345678');

        $this->assertDatabaseHas('users', [
            'phone' => '+254712345678',
            'phone_verified_at' => null,
            'is_active' => false,
        ]);

        $this->assertSame(1, PhoneVerificationOtp::where('phone', '+254712345678')->count());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://bulksms.talksasa.com/api/v3/sms/send'
                && $request->hasHeader('Authorization', 'Bearer test-talksasa-key')
                && $request['recipient'] === '254712345678'
                && $request['sender_id'] === 'VisibleDM'
                && $request['type'] === 'plain'
                && preg_match('/\d{6}/', (string) $request['message']);
        });
    }

    public function test_signup_otp_can_be_verified_and_wrong_codes_are_rejected(): void
    {
        $sentMessage = null;

        Http::fake(function (Request $request) use (&$sentMessage) {
            $sentMessage = $request['message'];

            return Http::response(['message_id' => 'sms-456'], 200);
        });

        $this->postJson('/api/v1/auth/signup', $this->signupPayload([
            'phone' => '+254712345679',
            'username' => 'otp_user_two',
            'email' => 'otp-two@example.com',
        ]))->assertOk();

        $this->postJson('/api/v1/auth/signup/otp/verify', [
            'phone' => '+254712345679',
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['otp']);

        preg_match('/\d{6}/', (string) $sentMessage, $matches);

        $response = $this->postJson('/api/v1/auth/signup/otp/verify', [
            'phone' => '+254712345679',
            'otp' => $matches[0],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.phone', '+254712345679')
            ->assertJsonPath('data.is_active', false);

        $this->assertNotNull(User::where('phone', '+254712345679')->first()->phone_verified_at);
        $this->assertNotNull(PhoneVerificationOtp::where('phone', '+254712345679')->first()->verified_at);
    }

    public function test_unverified_users_cannot_sign_in_even_when_active(): void
    {
        User::create([
            'fullname' => 'Unverified User',
            'username' => 'unverified_user',
            'email' => 'unverified@example.com',
            'phone' => '+254712345680',
            'password' => 'password123',
            'occupation' => 'Promoter',
            'gender' => 'Male',
            'role_id' => '11111111-1111-4111-8111-111111111111',
            'my_code' => '1234567890',
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/signin', [
            'username' => 'unverified_user',
            'password' => 'password123',
            'app_version' => 'test',
        ])->assertUnauthorized()
            ->assertJsonPath('requires_phone_verification', true);
    }

    private function signupPayload(array $overrides = []): array
    {
        return array_merge([
            'fullname' => 'OTP User',
            'username' => 'otp_user',
            'phone' => '+254712345678',
            'email' => 'otp@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'occupation' => 'Promoter',
            'gender' => 'Male',
            'county' => 'Nairobi',
        ], $overrides);
    }
}
