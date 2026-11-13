<?php

namespace Tests\Feature;

use App\Models\AdvertImages;
use App\Models\Campaign;
use App\Models\Counties;
use App\Models\SubCounty;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class AdvertAudienceGenderTest extends TestCase
{
    private User $male;

    private User $female;

    private Campaign $campaign;

    private array $otherLocation;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Http::preventStrayRequests();
        AdminPanelDatabase::build();
        // Keep normal authentication, but isolate audience matching from bearer-token version checks.
        $this->withoutMiddleware(\App\Http\Middleware\CheckActiveUser::class);
        $county = Counties::create(['name' => 'Member county', 'code' => 1]);
        $subcounty = SubCounty::create(['name' => 'Member subcounty', 'county_id' => $county->id]);
        $otherCounty = Counties::create(['name' => 'Other county', 'code' => 2]);
        $otherSubcounty = SubCounty::create(['name' => 'Other subcounty', 'county_id' => $otherCounty->id]);
        $this->otherLocation = ['county_id' => $otherCounty->id, 'subcounty_id' => $otherSubcounty->id];
        $this->male = AdminPanelDatabase::user('member');
        $this->female = AdminPanelDatabase::user('member');
        foreach (['Male' => $this->male, 'Female' => $this->female] as $gender => $user) {
            $user->update(['gender' => $gender, 'county_id' => $county->id, 'subcounty_id' => $subcounty->id]);
        }
        $this->campaign = Campaign::create(['name' => 'Gender targeting', 'owner_id' => $this->male->id]);
    }

    /** @dataProvider allGenderValues */
    public function test_all_gender_adverts_are_available_to_both_male_and_female_members(string|array $gender): void
    {
        // Nonmatching locations ensure a location fallback cannot hide a broken gender predicate.
        $all = $this->advert(['gender' => $gender] + $this->otherLocation);
        $maleOnly = $this->advert(['gender' => 'Male'] + $this->otherLocation);
        $femaleOnly = $this->advert(['gender' => 'Female'] + $this->otherLocation);
        $unrestricted = $this->advert(null);

        foreach ([[$this->male, $maleOnly], [$this->female, $femaleOnly]] as [$user, $matching]) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/campaign/get_product_advert?status=available')
                ->assertOk()->assertJsonPath('pagination.total', 3);

            $this->assertEqualsCanonicalizing(
                [$all->id, $matching->id, $unrestricted->id],
                array_column($response->json('data.data'), 'id'),
            );
        }
    }

    public static function allGenderValues(): array
    {
        return ['scalar gender' => ['all'], 'gender list' => [['all']]];
    }

    public function test_all_gender_does_not_bypass_expiry_or_the_current_members_started_adverts(): void
    {
        $audience = ['gender' => 'all'] + $this->otherLocation;
        $available = $this->advert($audience);
        $expired = $this->advert($audience);
        $expired->update(['valid_until' => now()->subHour()]);
        $started = $this->advert($audience);
        DB::table('screenshots')->insert([
            'id' => (string) Str::uuid(), 'advert_id' => $started->id, 'processed_by' => $this->male->id,
            'screenshot' => 'screenshots/started.png', 'timestamp' => 'Today, 10:00',
            'views' => 10, 'number' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([[$this->male, [$available->id]], [$this->female, [$available->id, $started->id]]] as [$user, $ids]) {
            $response = $this->actingAs($user, 'sanctum')
                ->getJson('/api/v1/campaign/get_product_advert?status=available')
                ->assertOk()->assertJsonPath('pagination.total', count($ids));
            $this->assertEqualsCanonicalizing($ids, array_column($response->json('data.data'), 'id'));
        }
    }

    private function advert(?array $audience): AdvertImages
    {
        return AdvertImages::create([
            'campaign_id' => $this->campaign->id, 'name' => 'Audience advert '.Str::uuid(),
            'description' => 'Audience filtering fixture', 'category' => 'General',
            'type' => 'IMAGE', 'image_path' => 'uploads/audience-test.png',
            'capital_invested' => 0, 'selling_price' => 0, 'capacity' => 100,
            'valid_until' => now()->addDay(), 'target_audience' => $audience,
        ]);
    }
}
