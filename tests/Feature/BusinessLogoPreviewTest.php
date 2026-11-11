<?php

namespace Tests\Feature;

use App\Filament\Resources\CampaignOwnerLogoResource;
use App\Filament\Resources\CampaignOwnerLogoResource\Pages\ManageCampaignOwnerLogos;
use App\Models\CampaignOwnerLogo;
use App\Models\CampaignOwnerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class BusinessLogoPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        Storage::fake('admin_media');
        AdminPanelDatabase::build();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->actingAs(AdminPanelDatabase::user());
    }

    public function test_business_logo_thumbnails_preserve_the_whole_image_and_open_the_preview(): void
    {
        $logo = $this->logo();

        Livewire::test(ManageCampaignOwnerLogos::class)
            ->assertCanSeeTableRecords([$logo])
            ->assertSeeHtml('src="'.Storage::disk('admin_media')->url($logo->logo_path).'"')
            ->assertSeeHtml('object-fit: contain;')
            ->assertSeeHtml('alt="Example business logo"')
            ->callTableColumnAction('logo_path', $logo)
            ->assertSet('mountedTableActions', ['preview'])
            ->assertSee('Open original image')
            ->assertSee('The checked background shows transparent areas');
    }

    public function test_preview_works_for_additional_logos_without_changing_the_primary_logo(): void
    {
        $primary = $this->logo();
        $additional = CampaignOwnerLogo::create([
            'profile_id' => $primary->profile_id,
            'logo_path' => UploadedFile::fake()->image('additional.png', 200, 800)->store('uploads/logos', 'admin_media'),
            'is_primary' => false,
        ]);
        $before = DB::table('campaign_owner_logos')->orderBy('id')->get()->toJson();

        Livewire::test(ManageCampaignOwnerLogos::class)
            ->mountTableAction('preview', $additional)
            ->assertSee('Additional business logo')
            ->assertSeeHtml('href="'.Storage::disk('admin_media')->url($additional->logo_path).'"')
            ->assertSeeHtml('object-fit: contain;');

        $this->assertSame($before, DB::table('campaign_owner_logos')->orderBy('id')->get()->toJson());
        $this->assertSame($primary->logo_path, $primary->profile->fresh()->logo_path);
    }

    public function test_missing_logo_files_have_a_helpful_preview_instead_of_a_broken_image(): void
    {
        $logo = $this->logo();
        Storage::disk('admin_media')->delete($logo->logo_path);

        Livewire::test(ManageCampaignOwnerLogos::class)->mountTableAction('preview', $logo)
            ->assertSuccessful()->assertSee('Logo preview is unavailable.')
            ->assertDontSee('Open original image');
    }

    public function test_preview_respects_business_filter_and_escapes_the_business_name(): void
    {
        $logo = $this->logo('<script>alert("business")</script>');
        $other = $this->logo('Another business');

        Livewire::test(ManageCampaignOwnerLogos::class)
            ->filterTable('profile_id', $logo->profile_id)
            ->assertCanSeeTableRecords([$logo])->assertCanNotSeeTableRecords([$other])
            ->mountTableAction('preview', $logo)
            ->assertSee('<script>alert("business")</script> — logo')
            ->assertDontSeeHtml('<script>alert("business")</script>');
    }

    public function test_business_logos_remain_restricted_to_active_authorized_staff(): void
    {
        $logo = $this->logo();
        $manager = AdminPanelDatabase::user('manager');
        $this->actingAs($manager);
        $this->get(CampaignOwnerLogoResource::getUrl('index'))->assertOk();
        Livewire::test(ManageCampaignOwnerLogos::class)->mountTableAction('preview', $logo)
            ->assertSuccessful()->assertSee('Primary business logo');

        $this->actingAs(AdminPanelDatabase::user('member'));
        $this->get(CampaignOwnerLogoResource::getUrl('index'))->assertForbidden();
        $manager->update(['is_active' => false]);
        $this->actingAs($manager);
        $this->get(CampaignOwnerLogoResource::getUrl('index'))->assertForbidden();
    }

    private function logo(string $businessName = 'Example business'): CampaignOwnerLogo
    {
        $owner = AdminPanelDatabase::user('campaign_owner');
        $path = UploadedFile::fake()->image('business-'.Str::uuid().'.png', 1000, 200)->store('uploads/logos', 'admin_media');
        $profile = CampaignOwnerProfile::create([
            'user_id' => $owner->id, 'business_name' => $businessName,
            'mpesa_phone' => $owner->phone, 'logo_path' => $path, 'account_status' => 'ACTIVE',
        ]);

        return CampaignOwnerLogo::create(['profile_id' => $profile->id, 'logo_path' => $path, 'is_primary' => true]);
    }
}
