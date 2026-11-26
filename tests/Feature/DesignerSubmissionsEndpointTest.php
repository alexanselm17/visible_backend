<?php

namespace Tests\Feature;

use App\Enums\AdvertSubmissionStatus as Status;
use App\Models\AdvertSubmission;
use App\Models\AdvertSubmissionMedia;
use App\Models\AppVersion;
use App\Models\Campaign;
use App\Models\CampaignOwnerLogo;
use App\Models\CampaignOwnerProfile;
use App\Models\TokenType;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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
class DesignerSubmissionsEndpointTest extends TestCase
{
    private const ENDPOINT = '/api/v1/campaign-owner/submissions/designer';

    private User $designer;

    private User $otherDesigner;

    private User $owner;

    private Campaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Http::preventStrayRequests();
        AdminPanelDatabase::build();
        AppVersion::create(['versions' => '1.0.0']);
        $this->designer = AdminPanelDatabase::user('designer');
        $this->otherDesigner = AdminPanelDatabase::user('designer');
        $this->owner = AdminPanelDatabase::user('campaign_owner');
        $this->campaign = Campaign::create(['name' => 'Designer test campaign', 'owner_id' => $this->owner->id]);
    }

    private function loginAs(User $user): void
    {
        Auth::forgetGuards();
        $this->withToken($user->createToken('api-token-v1.0.0')->plainTextToken);
    }

    private function submission(string $status = Status::PENDING_DESIGN, array $extra = []): AdvertSubmission
    {
        return AdvertSubmission::create(array_replace([
            'campaign_id' => $this->campaign->id, 'submitted_by' => $this->owner->id,
            'name' => 'Designer task', 'description' => 'Original campaign brief', 'capital_invested' => 0,
            'type' => 'IMAGE', 'target_reach' => 100, 'token_type_id' => TokenType::where('code', 'PLATINUM')->value('id'),
            'status' => $status, 'designed_by' => $this->designer->id,
        ], $extra));
    }

    public function test_default_endpoint_returns_only_the_logged_in_designers_history_across_all_statuses(): void
    {
        $expected = collect(Status::all())->map(fn ($status) => $this->submission($status));
        $other = $this->submission(Status::DESIGN_DONE, ['designed_by' => $this->otherDesigner->id]);
        $unassigned = $this->submission(extra: ['designed_by' => null]);
        $this->loginAs($this->designer);

        $response = $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('ok', true)
            ->assertJsonPath('designer_id', $this->designer->id)->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 5)->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('summary.total', 5);
        foreach (Status::all() as $status) {
            $response->assertJsonPath('summary.by_status.'.$status, 1);
        }
        $this->assertEqualsCanonicalizing($expected->pluck('id')->all(), array_column($response->json('data'), 'id'));
        $response->assertJsonMissing(['id' => $other->id])->assertJsonMissing(['id' => $unassigned->id]);
        $this->assertSame(7, AdvertSubmission::count());
        $this->assertNull($unassigned->fresh()->designed_by);
    }

    public function test_explicit_own_id_works_and_query_parameters_cannot_override_the_authenticated_scope(): void
    {
        $own = $this->submission(Status::PUBLISHED);
        $other = $this->submission(Status::PUBLISHED, ['designed_by' => $this->otherDesigner->id]);
        $this->loginAs($this->designer);
        $this->getJson(self::ENDPOINT.'/'.$this->designer->id.'?designer_id='.$this->otherDesigner->id)
            ->assertOk()->assertJsonPath('data.0.id', $own->id)->assertJsonPath('meta.total', 1)->assertJsonMissing(['id' => $other->id]);
        $this->getJson(self::ENDPOINT.'/'.$this->otherDesigner->id)->assertForbidden();
        $this->getJson(self::ENDPOINT.'/'.Str::uuid())->assertForbidden();
    }

    public function test_staff_can_inspect_other_designers_but_default_is_still_their_own_history(): void
    {
        $submission = $this->submission(Status::DESIGN_DONE);
        foreach (['admin', 'dev', 'manager'] as $role) {
            $this->loginAs(AdminPanelDatabase::user($role));
            $this->getJson(self::ENDPOINT.'/'.$this->designer->id)->assertOk()->assertJsonPath('data.0.id', $submission->id);
            $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('meta.total', 0);
        }
        $this->getJson(self::ENDPOINT.'/'.Str::uuid())->assertNotFound();
        $this->getJson(self::ENDPOINT.'/not-a-uuid')->assertNotFound();
    }

    public function test_real_authentication_active_account_and_role_checks_are_required(): void
    {
        $this->getJson(self::ENDPOINT)->assertUnauthorized();
        foreach (['member', 'campaign_owner', 'cashier'] as $role) {
            $this->loginAs(AdminPanelDatabase::user($role));
            $this->getJson(self::ENDPOINT)->assertForbidden();
            $this->getJson(self::ENDPOINT.'/'.$this->designer->id)->assertForbidden();
        }
        $this->designer->update(['is_active' => false]);
        $this->loginAs($this->designer);
        $this->getJson(self::ENDPOINT)->assertUnauthorized()->assertJsonPath('ok', false);
    }

    public function test_status_filter_affects_results_but_summary_retains_all_status_counts(): void
    {
        foreach (Status::all() as $status) {
            $this->submission($status);
        }
        $this->loginAs($this->designer);
        foreach ([Status::PENDING_DESIGN, Status::DESIGN_DONE, Status::PUBLISHED, Status::REJECTED] as $status) {
            $this->getJson(self::ENDPOINT.'?status='.$status)->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.status', $status)->assertJsonPath('meta.total', 1)
                ->assertJsonPath('summary.total', 5)->assertJsonPath('summary.by_status.'.Status::DESIGN_DONE, 1);
        }
        $this->getJson(self::ENDPOINT.'?status=all')->assertOk()->assertJsonCount(5, 'data');
    }

    public function test_campaign_owner_and_date_filters_are_applied_before_summary_and_include_whole_days(): void
    {
        $match = $this->submission(Status::DESIGN_DONE);
        $match->forceFill(['created_at' => '2026-11-26 23:59:59'])->save();
        $previous = $this->submission(Status::PENDING_DESIGN);
        $previous->forceFill(['created_at' => '2026-11-25 23:59:59'])->save();
        $later = $this->submission(Status::PUBLISHED);
        $later->forceFill(['created_at' => '2026-11-27 00:00:00'])->save();
        $differentCampaign = Campaign::create(['name' => 'Another campaign', 'owner_id' => $this->owner->id]);
        $this->submission(Status::DESIGN_DONE, ['campaign_id' => $differentCampaign->id]);
        $this->loginAs($this->designer);
        $query = http_build_query(['from' => '2026-11-26', 'to' => '2026-11-26',
            'campaign_id' => $this->campaign->id, 'submitted_by' => $this->owner->id]);
        $this->getJson(self::ENDPOINT.'?'.$query)->assertOk()->assertJsonPath('data.0.id', $match->id)
            ->assertJsonCount(1, 'data')->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.by_status.'.Status::PENDING_DESIGN, 0);
        $this->getJson(self::ENDPOINT.'?to=2026-11-25')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson(self::ENDPOINT.'?from=2026-11-27&campaign_id='.$this->campaign->id)->assertOk()->assertJsonPath('data.0.id', $later->id);
        $this->getJson(self::ENDPOINT.'?submitted_by='.Str::uuid())->assertOk()->assertJsonPath('summary.total', 0);
    }

    public function test_pagination_is_bounded_stable_and_does_not_change_summary_counts(): void
    {
        $submissions = collect(range(1, 5))->map(fn () => $this->submission(Status::DESIGN_DONE));
        foreach ($submissions as $submission) {
            $submission->forceFill(['created_at' => '2026-11-26 10:00:00'])->save();
        }
        $this->loginAs($this->designer);
        $ids = [];
        foreach ([1, 2, 3] as $page) {
            $response = $this->getJson(self::ENDPOINT.'?per_page=2&page='.$page)->assertOk()
                ->assertJsonPath('meta.current_page', $page)->assertJsonPath('meta.per_page', 2)
                ->assertJsonPath('meta.total', 5)->assertJsonPath('meta.last_page', 3)->assertJsonPath('summary.total', 5);
            $ids = array_merge($ids, array_column($response->json('data'), 'id'));
        }
        $this->assertSame($submissions->pluck('id')->sortDesc()->values()->all(), $ids);
        $this->getJson(self::ENDPOINT.'?per_page=2&page=4')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 5);
    }

    public function test_invalid_filters_return_validation_errors_instead_of_an_unbounded_query(): void
    {
        $this->loginAs($this->designer);
        foreach ([['status' => 'bad'], ['status' => ['DESIGN_DONE']], ['per_page' => 0], ['per_page' => 101],
            ['per_page' => 'abc'], ['page' => 0], ['campaign_id' => 'bad'], ['submitted_by' => 'bad'],
            ['from' => '2026-02-30'], ['to' => 'bad'], ['from' => '2026-11-26', 'to' => '2026-11-25']] as $invalid) {
            $field = array_key_last($invalid);
            $this->getJson(self::ENDPOINT.'?'.http_build_query($invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_original_media_branding_final_urls_and_limited_designer_details_are_returned(): void
    {
        $this->owner->update(['fcm_token' => 'PRIVATE-PUSH-TOKEN']);
        $submission = $this->submission(Status::DESIGN_DONE, ['final_image_path' => 'submissions/final/designed.png']);
        AdvertSubmissionMedia::create(['submission_id' => $submission->id, 'type' => 'IMAGE', 'path' => 'submissions/original/source.png', 'sort_order' => 0]);
        $profile = CampaignOwnerProfile::create(['user_id' => $this->owner->id, 'business_name' => 'Owner business', 'mpesa_phone' => $this->owner->phone]);
        CampaignOwnerLogo::create(['profile_id' => $profile->id, 'logo_path' => 'owner-logos/brand.png', 'is_primary' => true]);
        $this->loginAs($this->designer);
        $response = $this->getJson(self::ENDPOINT)->assertOk()
            ->assertJsonPath('data.0.original_images.0.url', asset('storage/submissions/original/source.png'))
            ->assertJsonPath('data.0.final_image_url', asset('storage/submissions/final/designed.png'))
            ->assertJsonPath('data.0.user.campaign_owner_profile.logos.0.logo_url', asset('storage/owner-logos/brand.png'))
            ->assertJsonPath('data.0.designer.id', $this->designer->id)->assertJsonPath('data.0.designer.fullname', $this->designer->fullname);
        $this->assertSame(['id', 'fullname'], array_keys($response->json('data.0.designer')));
        $response->assertJsonMissingPath('data.0.user.password')->assertJsonMissingPath('data.0.user.fcm_token')
            ->assertJsonMissingPath('data.0.user.phone')->assertJsonMissingPath('data.0.user.campaign_owner_profile.mpesa_phone');
    }

    public function test_empty_and_archived_designer_histories_are_read_only_and_the_shared_queue_is_unchanged(): void
    {
        $this->loginAs($this->designer);
        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('summary.total', 0)->assertJsonPath('summary.by_status.'.Status::DESIGN_DONE, 0);
        $unassigned = $this->submission(extra: ['designed_by' => null]);
        $completed = $this->submission(Status::DESIGN_DONE);
        $this->getJson('/api/v1/campaign-owner/submissions/pending')->assertOk()->assertJsonPath('data.0.id', $unassigned->id);
        $this->assertNull($unassigned->fresh()->designed_by);
        $this->designer->delete();
        $this->loginAs(AdminPanelDatabase::user('manager'));
        $this->getJson(self::ENDPOINT.'/'.$this->designer->id)->assertOk()->assertJsonPath('data.0.id', $completed->id)->assertJsonPath('summary.total', 1);
        $this->assertSame(2, AdvertSubmission::count());
        $this->assertSame($this->designer->id, $completed->fresh()->designed_by);
    }
}
