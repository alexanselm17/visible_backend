<?php

namespace Tests\Feature;

use App\Models\AdvertImages;
use App\Models\User;
use App\Services\ImageDecoderService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('advert_images', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('image_path')->nullable();
            $table->string('name');
            $table->timestamps();
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

            $this->assertSame(
                $user->my_code,
                app(ImageDecoderService::class)->decode($uploadedImage)
            );
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
}
