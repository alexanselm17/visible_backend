<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandingSettings;
use App\Filament\Resources\LegalDocumentResource;
use App\Filament\Resources\LegalDocumentResource\Pages\ManageLegalDocuments;
use App\Filament\Resources\LegalDocumentRevisionResource;
use App\Filament\Resources\PrivacyRequestResource;
use App\Filament\Resources\PrivacyRequestResource\Pages\ManagePrivacyRequests;
use App\Models\LegalDocument;
use App\Models\LegalDocumentRevision;
use App\Models\PrivacyRequest;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\ImageEncoderService;
use App\Services\InvisibleImageWatermarkService;
use App\Services\LegalPageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class PublicLegalPagesTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Queue::fake();
        AdminPanelDatabase::build();
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));
        $this->admin = AdminPanelDatabase::user();
    }

    public function test_policies_start_as_drafts_and_private_preview_never_leaks(): void
    {
        $this->get('/privacy-policy')->assertStatus(503)->assertSee('Not published yet')->assertDontSee('OpenAI');
        $this->get('/terms-and-conditions')->assertStatus(503);
        $document = LegalDocument::firstOrFail();
        $this->get(route('legal.preview', $document))->assertForbidden();
        $this->actingAs($this->admin)->get(route('legal.preview', $document))->assertOk()
            ->assertSee('Private draft preview')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_publication_requires_real_identity_permissions_and_retention_details(): void
    {
        $this->actingAs($this->admin);
        $document = LegalDocument::where('slug', 'privacy-policy')->firstOrFail();
        $document->update(['effective_on' => today()]);
        try {
            app(LegalPageService::class)->publish($document);
            $this->fail('Unconfirmed operator facts must block publication.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operator_name', $exception->errors());
            $this->assertArrayHasKey('accessibility_mode', $exception->errors());
            $this->assertArrayHasKey('retention_policy', $exception->errors());
        }
        $this->assertNull($document->fresh()->published_at);
        $this->assertSame(0, LegalDocumentRevision::count());
    }

    public function test_seeded_policies_can_be_published_without_editing_the_draft_date_first(): void
    {
        $this->configureIdentity();
        foreach (LegalDocument::all() as $document) {
            $this->assertNull($document->effective_on);
            Livewire::test(ManageLegalDocuments::class)
                ->mountTableAction('publish', $document)
                ->assertTableActionDataSet(['effective_on' => today()->toDateString()])
                ->setTableActionData(['effective_on' => today()->toDateString(), 'reviewed' => true])
                ->callMountedTableAction()->assertHasNoTableActionErrors();
            $document->refresh();
            $this->assertSame(today()->toDateString(), $document->effective_on->toDateString());
            $this->assertSame(today()->toDateString(), $document->published_effective_on->toDateString());
            $this->assertSame($this->admin->id, $document->updated_by);
            $this->assertSame(today()->toDateString(), $document->revisions()->firstOrFail()->effective_on->toDateString());
            $this->get('/'.$document->slug)->assertOk()->assertSee(today()->format('j F Y'));
        }
    }

    public function test_publish_dialog_keeps_the_saved_effective_date_and_allows_a_different_date(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        $originalDate = today()->subDays(10)->toDateString();
        $newDate = today()->subDay()->toDateString();
        $document->update(['effective_on' => $originalDate]);
        app(LegalPageService::class)->publish($document);
        Livewire::test(ManageLegalDocuments::class)
            ->mountTableAction('publish', $document)
            ->assertTableActionDataSet(['effective_on' => $originalDate])
            ->setTableActionData(['effective_on' => $newDate, 'reviewed' => true])
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        $document->refresh();
        $this->assertSame($newDate, $document->effective_on->toDateString());
        $this->assertSame($newDate, $document->published_effective_on->toDateString());
        $this->assertSame($originalDate, $document->revisions()->where('version', 1)->firstOrFail()->effective_on->toDateString());
        $this->assertSame($newDate, $document->revisions()->where('version', 2)->firstOrFail()->effective_on->toDateString());
    }

    public function test_publish_dialog_rejects_missing_or_future_dates_without_writing_anything(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        foreach ([null, 'not-a-date', today()->addDay()->toDateString()] as $date) {
            Livewire::test(ManageLegalDocuments::class)->callTableAction('publish', $document, [
                'effective_on' => $date, 'reviewed' => true,
            ])->assertHasTableActionErrors(['effective_on']);
            $this->assertNull($document->fresh()->effective_on);
            $this->assertNull($document->fresh()->published_at);
            $this->assertSame(0, $document->revisions()->count());
        }
    }

    public function test_failed_publication_does_not_save_the_date_selected_in_the_dialog(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        $document->update(['draft_content' => '<p>[Insert policy text]</p>']);
        Livewire::test(ManageLegalDocuments::class)->callTableAction('publish', $document, [
            'effective_on' => today()->toDateString(), 'reviewed' => true,
        ])->assertHasTableActionErrors();
        $this->assertNull($document->fresh()->effective_on);
        $this->assertNull($document->fresh()->updated_by);
        $this->assertSame(0, $document->revisions()->count());
    }

    public function test_publish_dialog_still_requires_confirmation_before_saving_the_date(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        Livewire::test(ManageLegalDocuments::class)->callTableAction('publish', $document, [
            'effective_on' => today()->toDateString(), 'reviewed' => false,
        ])->assertHasTableActionErrors(['reviewed']);
        $this->assertNull($document->fresh()->effective_on);
        $this->assertNull($document->fresh()->published_at);
        $this->assertSame(0, $document->revisions()->count());
    }

    public function test_publication_service_rejects_an_explicit_future_date(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        try {
            app(LegalPageService::class)->publish($document, today()->addDay()->toDateString());
            $this->fail('An explicit future date must be rejected by the service too.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_on', $exception->errors());
        }
        $this->assertNull($document->fresh()->effective_on);
        $this->assertSame(0, $document->revisions()->count());
    }

    public function test_published_pages_are_anonymous_html_with_actual_backend_disclosures(): void
    {
        $this->configureIdentity();
        foreach (LegalDocument::all() as $document) {
            $document->update(['effective_on' => today()]);
            app(LegalPageService::class)->publish($document);
        }
        auth()->logout();
        $this->get('/privacy-policy')->assertOk()->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Visible Test Operator')->assertSee('TalkSasa')->assertSee('OpenAI')->assertSee('Google Firebase')
            ->assertSee('invisible watermark')->assertSee('delete-account')->assertSee('Version 1')
            ->assertSee('privacy@example.test')->assertDontSee('[APP_NAME]')->assertDontSee('[RETENTION_POLICY]')
            ->assertDontSee('duly registered')->assertDontSee('Twilio');
        $this->get('/terms-and-conditions')->assertOk()->assertSee('not pay per advert')
            ->assertSee('multiplication model')->assertSee('Campaign tokens and charges');
        $this->get('/privacy')->assertRedirect('/privacy-policy');
        $this->get('/terms')->assertRedirect('/terms-and-conditions');
    }

    public function test_saved_drafts_do_not_change_live_pages_and_publication_keeps_history(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::where('slug', 'privacy-policy')->firstOrFail();
        $document->update(['effective_on' => today()]);
        app(LegalPageService::class)->publish($document);
        $original = $document->fresh()->published_content;
        Livewire::test(ManageLegalDocuments::class)->callTableAction('edit', $document, [
            'title' => 'Revised privacy policy', 'effective_on' => today()->toDateString(),
            'draft_content' => '<h2>Updated privacy details</h2><p>'.str_repeat('A meaningful revised policy sentence. ', 15).'</p>',
        ])->assertHasNoTableActionErrors();
        $this->assertSame($original, $document->fresh()->published_content);
        $this->get('/privacy-policy')->assertOk()->assertDontSee('Updated privacy details');
        Livewire::test(ManageLegalDocuments::class)->callTableAction('publish', $document, ['reviewed' => true])->assertHasNoTableActionErrors();
        $this->get('/privacy-policy')->assertOk()->assertSee('Updated privacy details')->assertSee('Version 2');
        $this->assertSame(2, $document->revisions()->count());
        $this->assertSame($original, $document->revisions()->where('version', 1)->firstOrFail()->content);
        $this->assertSame('Visible Test Operator', $document->revisions()->firstOrFail()->identity_snapshot['operator_name']);
        $this->assertFalse(LegalDocumentResource::canDelete($document));
        $this->assertFalse(LegalDocumentRevisionResource::canEdit($document->revisions()->firstOrFail()));
    }

    public function test_unsafe_html_is_removed_from_drafts_preview_and_public_pages(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        $html = '<h2 onclick="alert(1)">Safe heading</h2><p>'.str_repeat('Meaningful policy text. ', 20).'</p>'
            .'<script>alert("secret")</script><iframe src="https://evil.test"></iframe><img src="https://evil.test/track" onerror="alert(1)">'
            .'<a href="javascript:alert(1)">Bad link</a><form action="https://evil.test">Bad form</form>';
        $document->update(['draft_content' => $html, 'effective_on' => today()]);
        $this->get(route('legal.preview', $document))->assertOk()->assertSee('Safe heading')
            ->assertDontSee('onclick=', false)->assertDontSee('javascript:', false)->assertDontSee('evil.test');
        app(LegalPageService::class)->publish($document);
        $this->get('/'.$document->slug)->assertOk()->assertDontSee('<script', false)->assertDontSee('onerror=', false);
        $this->assertStringNotContainsString('<script', $document->fresh()->published_content);
    }

    public function test_accessibility_disclosure_and_placeholders_are_validated(): void
    {
        $this->configureIdentity();
        SiteSetting::current()->update(['accessibility_mode' => 'whatsapp_status', 'accessibility_disclosure' => null]);
        $document = LegalDocument::firstOrFail();
        $document->update(['effective_on' => today()]);
        try {
            app(LegalPageService::class)->publish($document);
            $this->fail('Missing sensitive permission disclosure must block publication.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('accessibility_disclosure', $exception->errors());
        }
        SiteSetting::current()->update(['accessibility_disclosure' => 'With separate permission the app reads only the selected WhatsApp Status view count screen; the user may disable this in Android settings.']);
        $document->update(['draft_content' => $document->draft_content.'<p>[Insert Date]</p>']);
        $this->expectException(ValidationException::class);
        app(LegalPageService::class)->publish($document);
    }

    public function test_logo_upload_and_branding_are_shared_with_the_public_and_admin_pages(): void
    {
        Storage::fake('admin_media');
        $this->actingAs($this->admin);
        Livewire::test(BrandingSettings::class)->fillForm([
            'app_name' => 'Visible New Brand', 'operator_name' => 'Visible Test Operator',
            'support_email' => 'help@example.test', 'accessibility_mode' => 'unconfirmed',
            'logo_path' => UploadedFile::fake()->image('brand.png', 320, 120),
        ])->call('save')->assertHasNoFormErrors();
        $settings = SiteSetting::current();
        $this->assertSame('Visible New Brand', $settings->app_name);
        $this->assertStringStartsWith('branding/', $settings->logo_path);
        Storage::disk('admin_media')->assertExists($settings->logo_path);
        $this->assertNotNull($settings->logoLocalPath());
        $this->get('/delete-account')->assertOk()->assertSee($settings->logoUrl(), false)->assertSee('Visible New Brand');
        $panel = \Filament\Facades\Filament::getPanel('admin');
        $this->assertSame('Visible New Brand Workspace', $panel->getBrandName());
        $this->assertSame($settings->logoUrl(), $panel->getBrandLogo());
    }

    public function test_svg_logo_and_unsafe_stored_paths_are_rejected(): void
    {
        Storage::fake('admin_media');
        $this->actingAs($this->admin);
        Livewire::test(BrandingSettings::class)->fillForm([
            'logo_path' => UploadedFile::fake()->createWithContent('evil.svg', '<svg onload="alert(1)"></svg>'),
        ])->call('save')->assertHasFormErrors(['logo_path']);
        $this->assertNull(SiteSetting::current()->logo_path);
        $this->assertNull((new SiteSetting(['logo_path' => '../private.png']))->logoLocalPath());
    }

    public function test_admin_pages_render_and_non_admins_cannot_edit_or_view_requests(): void
    {
        $this->actingAs($this->admin);
        foreach ([BrandingSettings::getUrl(), LegalDocumentResource::getUrl(), LegalDocumentRevisionResource::getUrl(), PrivacyRequestResource::getUrl()] as $url) {
            $this->get($url)->assertOk();
        }
        $manager = AdminPanelDatabase::user('manager');
        $this->actingAs($manager);
        $this->withSession(['password_hash_web' => $manager->password]);
        $this->assertFalse(BrandingSettings::canAccess());
        $this->assertFalse(LegalDocumentResource::canViewAny());
        $this->assertFalse(PrivacyRequestResource::canViewAny());
        $this->get(BrandingSettings::getUrl())->assertForbidden();
        $this->get(PrivacyRequestResource::getUrl())->assertForbidden();
        $this->get(route('legal.preview', LegalDocument::first()))->assertForbidden();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(LegalPageService::class)->saveSettings(['app_name' => 'Unauthorized change']);
    }

    public function test_deletion_request_needs_no_login_and_cannot_delete_or_enumerate_accounts(): void
    {
        $this->get('/delete-account')->assertOk()->assertSee('Request account &amp; data deletion', false);
        $this->post('/privacy-request', [
            'type' => 'deletion', 'contact' => $this->admin->email, 'message' => 'Please delete my account.', 'confirmation' => 1,
        ])->assertRedirect('/delete-account')->assertSessionHas('request_reference');
        $request = PrivacyRequest::firstOrFail();
        $this->assertSame('new', $request->status);
        $this->assertSame($this->admin->email, $request->contact);
        $this->assertNull($this->admin->fresh()->deleted_at);
        $this->assertTrue($this->admin->fresh()->is_active);
        $this->get('/delete-account')->assertSee($request->reference)->assertDontSee($request->contact);
        $this->assertDatabaseCount('privacy_requests', 1);
    }

    public function test_request_form_validation_rate_limit_and_private_inbox(): void
    {
        $this->post('/privacy-request', ['type' => 'deletion'])->assertSessionHasErrors(['contact', 'confirmation']);
        $data = ['type' => 'access', 'contact' => 'user@example.test', 'confirmation' => 1];
        $this->post('/privacy-request', $data + ['website' => 'spam'])->assertSessionHasErrors('website');
        for ($i = 0; $i < 3; $i++) {
            $this->post('/privacy-request', $data)->assertRedirect('/privacy-request');
        }
        $this->post('/privacy-request', $data)->assertStatus(429);
        $this->get('/privacy-request')->assertOk()->assertDontSee('user@example.test');
        $this->assertDatabaseCount('privacy_requests', 3);
    }

    public function test_public_request_post_requires_a_csrf_token_in_production(): void
    {
        // Laravel normally bypasses CSRF in unit tests; enable the production path here.
        $this->app->instance('env', 'production');
        $this->post('/privacy-request', [
            'type' => 'deletion', 'contact' => 'member@example.test', 'confirmation' => 1,
        ])->assertStatus(419);
        $this->assertDatabaseCount('privacy_requests', 0);
    }

    public function test_privacy_request_completion_requires_fulfillment_confirmation(): void
    {
        $this->actingAs($this->admin);
        $request = PrivacyRequest::create(['type' => 'deletion', 'contact' => 'test@example.test']);
        Livewire::test(ManagePrivacyRequests::class)->callTableAction('handle', $request, [
            'status' => 'completed', 'staff_notes' => 'No ownership verification performed.', 'fulfilled' => false,
        ])->assertHasTableActionErrors();
        $this->assertSame('new', $request->fresh()->status);
        Livewire::test(ManagePrivacyRequests::class)->callTableAction('handle', $request, [
            'status' => 'in_review', 'staff_notes' => 'Contacted the registered phone to confirm ownership.',
        ])->assertHasNoTableActionErrors();
        $this->assertSame('in_review', $request->fresh()->status);
        $this->assertNull($request->fresh()->completed_at);
        Livewire::test(ManagePrivacyRequests::class)->callTableAction('handle', $request, [
            'status' => 'completed', 'staff_notes' => 'Ownership confirmed, deletion fulfilled and retention exceptions explained to the member.', 'fulfilled' => true,
        ])->assertHasNoTableActionErrors();
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame($this->admin->id, $request->fresh()->processed_by);
        $this->assertNotNull($request->fresh()->completed_at);
        $this->assertFalse(PrivacyRequestResource::canDelete($request));
    }

    public function test_legal_migration_is_repeatable_and_preserves_edited_and_published_records(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        $document->update(['title' => 'Operator reviewed policy', 'effective_on' => today()]);
        app(LegalPageService::class)->publish($document);
        $request = PrivacyRequest::create(['type' => 'access', 'contact' => 'test@example.test']);
        $migration = require database_path('migrations/2026_11_04_100000_create_public_legal_pages.php');
        $migration->down();
        $migration->up();
        $migration->up();
        $this->assertSame(2, LegalDocument::count());
        $this->assertSame(1, LegalDocumentRevision::count());
        $this->assertSame('Operator reviewed policy', $document->fresh()->title);
        $this->assertNotNull($document->fresh()->published_at);
        $this->assertSame('Visible Test Operator', SiteSetting::current()->operator_name);
        $this->assertDatabaseHas('privacy_requests', ['id' => $request->id]);
    }

    public function test_publishing_blocks_future_effective_dates_and_live_identity_cannot_be_blanked(): void
    {
        $this->configureIdentity();
        $document = LegalDocument::firstOrFail();
        $document->update(['effective_on' => today()->addDay()]);
        try {
            app(LegalPageService::class)->publish($document);
            $this->fail('A future-dated policy should not silently replace the current policy.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('effective_on', $exception->errors());
        }
        $document->update(['effective_on' => today()]);
        app(LegalPageService::class)->publish($document);
        $this->expectException(ValidationException::class);
        app(LegalPageService::class)->saveSettings([
            'app_name' => 'Visible', 'operator_name' => null, 'support_email' => null,
            'accessibility_mode' => 'unconfirmed',
        ]);
    }

    public function test_new_brand_logo_encodes_without_breaking_hidden_ownership_proof(): void
    {
        Storage::fake('admin_media');
        $this->actingAs($this->admin);
        $logo = UploadedFile::fake()->image('logo.png', 320, 120);
        $path = $logo->storeAs('branding', 'fixture-logo.png', 'admin_media');
        app(LegalPageService::class)->saveSettings(['app_name' => 'Visible', 'logo_path' => $path, 'accessibility_mode' => 'unconfirmed']);
        $source = UploadedFile::fake()->image('source.png', 640, 900);
        $result = app(ImageEncoderService::class)->encode($source->getPathname(), 'test-user-advert-proof');
        try {
            $this->assertSame([1080, 1350], array_slice(getimagesize($result['path']), 0, 2));
            $this->assertSame('test-user-advert-proof', app(InvisibleImageWatermarkService::class)->extract($result['path']));
        } finally {
            // Delete only this test's generated image, never the shared media directory.
            @unlink($result['path']);
        }
    }

    private function configureIdentity(): void
    {
        $this->actingAs($this->admin);
        app(LegalPageService::class)->saveSettings([
            'app_name' => 'Visible', 'operator_name' => 'Visible Test Operator',
            'support_email' => 'help@example.test', 'privacy_email' => 'privacy@example.test',
            'accessibility_mode' => 'none',
            // Test fixture only, not a production retention claim.
            'retention_policy' => 'TEST SCHEDULE: Active account information is kept while the account is used. Verified requests are handled within 30 days. Financial records are retained only for a documented lawful period. Logs, media, backups and request records are deleted under the verified operational schedule.',
        ]);
    }
}
