<?php

namespace Tests\Feature;

use App\Models\AdvertImages;
use App\Models\User;
use App\Services\AdvertQrCodeService;
use App\Services\ImageDecoderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class PersonalizedAdvertDownloadTest extends TestCase
{
    private string $sourceDirectory;

    private string $sourcePath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('q', 32)));
        config()->set('services.visible_qr.public_url', 'https://www.visibledm.com');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('advert_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('image_path')->nullable();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('advert_qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('advert_id');
            $table->string('identifier_snapshot', 10);
            $table->char('token_hash', 64)->unique();
            $table->timestamp('generated_at');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'advert_id']);
        });

        $this->sourceDirectory = public_path('storage/personalized-advert-tests');
        $this->sourcePath = $this->sourceDirectory.'/source.png';
        File::ensureDirectoryExists($this->sourceDirectory);
        File::copy(public_path('images/base_template.png'), $this->sourcePath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceDirectory);
        parent::tearDown();
    }

    public function test_download_contains_the_authenticated_users_qr_identifier(): void
    {
        $advert = AdvertImages::create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Personalized Test Advert',
            'image_path' => 'personalized-advert-tests/source.png',
        ]);
        $user = new User;
        $user->id = '22222222-2222-4222-8222-222222222222';
        $user->my_code = '1234567890';

        $response = $this->withoutMiddleware()
            ->actingAs($user, 'sanctum')
            ->get(route('download.advert.personalized', ['advertId' => $advert->id]));

        $response->assertOk();
        $response->assertHeader('content-type', 'image/png');
        $cacheControl = (string) $response->headers->get('cache-control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString(
            'personalized-test-advert-personalized.png',
            (string) $response->headers->get('content-disposition')
        );

        $encodedPath = $response->baseResponse->getFile()->getPathname();

        try {
            $uploadedImage = new UploadedFile(
                $encodedPath,
                basename($encodedPath),
                'image/png',
                null,
                true
            );

            $decodedUrl = app(ImageDecoderService::class)->decode($uploadedImage);
            $this->assertStringStartsWith('https://www.visibledm.com?qr=', (string) $decodedUrl);

            $verified = app(AdvertQrCodeService::class)->verify(
                (string) $decodedUrl,
                $user,
                $advert
            );

            $this->assertNotNull($verified);
            $this->assertSame($user->id, $verified->user_id);
            $this->assertSame($advert->id, $verified->advert_id);
            $this->assertSame($user->my_code, $verified->identifier_snapshot);
            $this->assertNotNull($verified->last_verified_at);

            $verificationResponse = $this->post('/api/v1/image/decode', [
                'advert_id' => $advert->id,
                'screenshot' => new UploadedFile(
                    $encodedPath,
                    basename($encodedPath),
                    'image/png',
                    null,
                    true
                ),
            ], ['Accept' => 'application/json']);

            $verificationResponse
                ->assertOk()
                ->assertJsonPath('identifier', $user->my_code)
                ->assertJsonPath('advert_id', $advert->id);

            $otherAdvert = AdvertImages::create([
                'id' => '77777777-7777-4777-8777-777777777777',
                'name' => 'Different Advert',
                'image_path' => 'personalized-advert-tests/source.png',
            ]);

            $this->post('/api/v1/image/decode', [
                'advert_id' => $otherAdvert->id,
                'screenshot' => new UploadedFile(
                    $encodedPath,
                    basename($encodedPath),
                    'image/png',
                    null,
                    true
                ),
            ])->assertUnprocessable()
                ->assertJsonPath('message', 'The QR code belongs to a different advert.')
                ->assertJsonPath('errors.advert_id.0', 'The QR code belongs to a different advert.');

            $otherUser = new User;
            $otherUser->id = '88888888-8888-4888-8888-888888888888';
            $otherUser->my_code = '0987654321';

            $this->actingAs($otherUser, 'sanctum')
                ->post('/api/v1/image/decode', [
                    'advert_id' => $advert->id,
                    'screenshot' => new UploadedFile(
                        $encodedPath,
                        basename($encodedPath),
                        'image/png',
                        null,
                        true
                    ),
                ])->assertUnprocessable()
                ->assertJsonPath('message', 'The QR code belongs to a different user account.')
                ->assertJsonPath('errors.identifier.0', 'The QR code belongs to a different user account.');
        } finally {
            File::delete($encodedPath);
        }
    }

    public function test_download_rejects_an_account_without_a_valid_identifier(): void
    {
        $advert = AdvertImages::create([
            'id' => '33333333-3333-4333-8333-333333333333',
            'name' => 'Invalid Identifier Advert',
            'image_path' => 'personalized-advert-tests/source.png',
        ]);
        $user = new User;
        $user->id = '44444444-4444-4444-8444-444444444444';
        $user->my_code = 'invalid';

        $this->withoutMiddleware()
            ->actingAs($user, 'sanctum')
            ->getJson(route('download.advert.personalized', ['advertId' => $advert->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Your account does not have a valid 10-digit QR identifier.');
    }

    public function test_qr_token_cannot_be_reused_for_another_user_or_advert(): void
    {
        $firstAdvert = AdvertImages::create([
            'name' => 'First Advert',
            'image_path' => 'personalized-advert-tests/source.png',
        ]);
        $secondAdvert = AdvertImages::create([
            'name' => 'Second Advert',
            'image_path' => 'personalized-advert-tests/source.png',
        ]);
        $owner = new User;
        $owner->id = '55555555-5555-4555-8555-555555555555';
        $owner->my_code = '1234567890';
        $otherUser = new User;
        $otherUser->id = '66666666-6666-4666-8666-666666666666';
        $otherUser->my_code = '0987654321';

        $service = app(AdvertQrCodeService::class);
        $url = $service->issue($owner, $firstAdvert);

        $this->assertNull($service->verify($url, $owner, $secondAdvert));
        $this->assertNull($service->verify($url, $otherUser, $firstAdvert));

        try {
            $service->verifyOrFail($url, $owner, $secondAdvert);
            $this->fail('A QR code from another advert should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'advert_id' => ['The QR code belongs to a different advert.'],
            ], $exception->errors());
        }

        try {
            $service->verifyOrFail($url, $otherUser, $firstAdvert);
            $this->fail('A QR code from another user should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'identifier' => ['The QR code belongs to a different user account.'],
            ], $exception->errors());
        }

        $replacement = str_ends_with($url, 'A') ? 'B' : 'A';
        $tamperedUrl = substr($url, 0, -1).$replacement;
        $this->assertNull($service->resolve($tamperedUrl));

        try {
            $service->verifyOrFail($tamperedUrl, $owner, $firstAdvert);
            $this->fail('A modified QR token should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame([
                'qr_code' => ['The QR code is missing, invalid, expired, or has been altered.'],
            ], $exception->errors());
        }
    }
}
