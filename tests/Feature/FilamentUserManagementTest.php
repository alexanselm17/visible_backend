<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Permission;
use App\Models\PhoneVerificationOtp;
use App\Models\User;
use App\Models\UserAccountEvent;
use App\Services\AdminUserService;
use App\Services\PhoneOtpService;
use App\Services\TalkSasaSmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use Tests\Support\AdminPanelDatabase;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class FilamentUserManagementTest extends TestCase
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
        $this->actingAs($this->admin);
    }

    public function test_all_users_create_button_role_filters_search_and_all_page_size(): void
    {
        $owner = AdminPanelDatabase::user('campaign_owner');
        $member = AdminPanelDatabase::user('member');
        $member->update(['is_active' => false]);
        $noRole = AdminPanelDatabase::user('member');
        $noRole->update(['role_id' => null]);

        Livewire::test(ListUsers::class)->assertActionVisible('create')
            ->assertCanSeeTableRecords([$owner, $member, $noRole])
            ->filterTable('role_id', $owner->role_id)->assertCanSeeTableRecords([$owner])
            ->assertCanNotSeeTableRecords([$member, $noRole])
            ->resetTableFilters()->filterTable('no_role')->assertCanSeeTableRecords([$noRole])
            ->assertCanNotSeeTableRecords([$owner, $member])
            ->resetTableFilters()->searchTable($member->username)
            ->assertCanSeeTableRecords([$member])->assertCanNotSeeTableRecords([$owner])
            ->searchTable('')->set('tableRecordsPerPage', 'all')->assertCanSeeTableRecords([$owner, $member, $noRole]);
    }

    public function test_activate_and_deactivate_are_explicit_audited_and_revoke_sessions(): void
    {
        $member = AdminPanelDatabase::user('member');
        $member->createToken('api-token-v1.0.0');
        DB::table('sessions')->insert([
            'id' => 'test-user-session', 'user_id' => $member->id, 'payload' => 'private-session-payload', 'last_activity' => time(),
        ]);
        Livewire::test(ListUsers::class)->callTableAction('deactivate', $member, ['reason' => 'Temporarily suspended for review'])
            ->assertHasNoTableActionErrors();
        $member->refresh();
        $this->assertFalse($member->is_active);
        $this->assertSame(0, $member->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
        $this->assertDatabaseHas('user_account_events', ['user_id' => $member->id, 'action' => 'account_deactivated', 'performed_by' => $this->admin->id]);
        Livewire::test(ListUsers::class)->callTableAction('activate', $member)->assertHasNoTableActionErrors();
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_account_phone_and_email_verification_controls_have_separate_audits(): void
    {
        $member = AdminPanelDatabase::user('member');
        Livewire::test(ListUsers::class)->callTableAction('verify_account', $member, ['reason' => 'Account details checked'])
            ->assertHasNoTableActionErrors();
        $member->refresh();
        $this->assertTrue($member->is_verified);
        $this->assertNull($member->phone_verified_at);
        Livewire::test(ListUsers::class)->callTableAction('mark_phone_verified', $member, ['reason' => 'Confirmed ownership during support call'])
            ->assertHasNoTableActionErrors();
        $member->refresh();
        $this->assertNotNull($member->phone_verified_at);
        Livewire::test(ListUsers::class)->callTableAction('verify_email', $member, ['reason' => 'Ownership evidence checked'])
            ->assertHasNoTableActionErrors();
        $member->refresh();
        $this->assertNotNull($member->email_verified_at);
        Livewire::test(ListUsers::class)->callTableAction('reset_phone_verification', $member, ['reason' => 'Ownership needs rechecking'])
            ->assertHasNoTableActionErrors();
        $this->assertNull($member->fresh()->phone_verified_at);
        $this->assertTrue($member->fresh()->is_verified);
        $this->assertDatabaseHas('user_account_events', ['user_id' => $member->id, 'action' => 'phone_verified']);
        $this->assertDatabaseHas('user_account_events', ['user_id' => $member->id, 'action' => 'email_verified']);
    }

    public function test_phone_otp_send_and_verification_use_talksasa_and_hide_the_code(): void
    {
        $member = AdminPanelDatabase::user('member');
        $code = null;
        $sms = Mockery::mock(TalkSasaSmsService::class);
        $sms->shouldReceive('send')->once()->andReturnUsing(function ($phone, $message) use ($member, &$code) {
            $this->assertSame($member->phone, $phone);
            preg_match('/\b\d{6}\b/', $message, $matches);
            $code = $matches[0];

            return 'TEST-DELIVERY';
        });
        $this->app->instance(TalkSasaSmsService::class, $sms);
        Livewire::test(ListUsers::class)->callTableAction('resend_otp', $member)->assertHasNoTableActionErrors();
        $otp = PhoneVerificationOtp::where('user_id', $member->id)->firstOrFail();
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        Livewire::test(ListUsers::class)->callTableAction('verify_phone_otp', $member, ['otp' => $code])
            ->assertHasNoTableActionErrors();
        $this->assertNotNull($member->fresh()->phone_verified_at);
        $this->assertTrue($member->fresh()->is_verified);
        $this->assertNotNull($otp->fresh()->verified_at);
        $this->get(UserResource::getUrl('view', ['record' => $member]))->assertOk()->assertDontSee($otp->code_hash);
        $this->assertSame('otp', UserAccountEvent::where('user_id', $member->id)->where('action', 'phone_verified')->firstOrFail()->metadata['method']);
    }

    public function test_editing_contacts_and_password_clears_stale_verification_and_sessions(): void
    {
        $member = AdminPanelDatabase::user('member');
        $member->forceFill(['phone_verified_at' => now(), 'email_verified_at' => now(), 'remember_token' => 'private-remember-token'])->save();
        $member->createToken('api-token-v1.0.0');
        $otp = $this->otp($member);
        Livewire::test(EditUser::class, ['record' => $member->id])->fillForm([
            'phone' => '+254711000111', 'email' => 'updated-user@example.test', 'password' => 'updated-strong-password',
        ])->call('save')->assertHasNoFormErrors();
        $member->refresh();
        $this->assertNull($member->phone_verified_at);
        $this->assertNull($member->email_verified_at);
        $this->assertNull($member->remember_token);
        $this->assertTrue(Hash::check('updated-strong-password', $member->password));
        $this->assertSame(0, $member->tokens()->count());
        $this->assertTrue($otp->fresh()->expires_at->lessThanOrEqualTo(now()));
        $audit = UserAccountEvent::where('user_id', $member->id)->where('action', 'profile_updated')->firstOrFail();
        $this->assertContains('password', $audit->metadata['changed_fields']);
        $this->assertStringNotContainsString('updated-strong-password', json_encode($audit->toArray()));
    }

    public function test_delete_is_recoverable_history_remains_and_deleted_tab_works(): void
    {
        $member = AdminPanelDatabase::user('member');
        $period = app(\App\Services\RewardPeriodService::class)->currentOrCreate($member->id);
        Livewire::test(ListUsers::class)->callTableAction('delete', $member, ['reason' => 'User requested account closure'])
            ->assertHasNoTableActionErrors();
        $member = User::withTrashed()->findOrFail($member->id);
        $this->assertTrue($member->trashed());
        $this->assertFalse($member->is_active);
        $this->assertDatabaseHas('reward_periods', ['id' => $period->id]);
        Livewire::test(ListUsers::class)->assertCanNotSeeTableRecords([$member])
            ->set('activeTab', 'deleted')->assertCanSeeTableRecords([$member])
            ->callTableAction('restore', $member)->assertHasNoTableActionErrors();
        $member->refresh();
        $this->assertFalse($member->trashed());
        $this->assertFalse($member->is_active);
        $this->assertDatabaseHas('user_account_events', ['user_id' => $member->id, 'action' => 'account_restored']);
    }

    public function test_manager_cannot_change_verification_permissions_or_staff_accounts(): void
    {
        $manager = AdminPanelDatabase::user('manager');
        $member = AdminPanelDatabase::user('member');
        $permission = Permission::create(['name' => 'Manage users', 'slug' => 'manage_users', 'category' => 'Users']);
        $manager->permissions()->attach($permission);
        $this->actingAs($manager);
        Livewire::test(ListUsers::class)->assertActionHidden('create')
            ->assertTableActionHidden('edit', $this->admin)
            ->assertTableActionHidden('verify_account', $member)
            ->assertTableActionHidden('mark_phone_verified', $member)
            ->assertTableActionHidden('delete', $member);
        app(AdminUserService::class)->update($member, [
            'role_id' => $this->admin->role_id, 'permissions' => [$permission->id], 'is_verified' => true,
        ]);
        $this->assertSame('member', $member->fresh()->role->slug);
        $this->assertFalse($member->fresh()->is_verified);
        $this->assertSame(0, $member->permissions()->count());
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(AdminUserService::class)->setVerification($member, 'phone', true, 'Unauthorized attempt');
    }

    public function test_bulk_changes_are_atomic_and_cannot_deactivate_the_acting_staff_member(): void
    {
        $member = AdminPanelDatabase::user('member');
        $member->createToken('api-token-v1.0.0');
        try {
            app(AdminUserService::class)->bulk(collect([$member, $this->admin]), 'deactivate', 'Batch test');
            $this->fail('Expected self-deactivation to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user', $exception->errors());
        }
        $this->assertTrue($member->fresh()->is_active);
        $this->assertSame(1, $member->tokens()->count());
        $this->assertSame(0, UserAccountEvent::where('user_id', $member->id)->count());
    }

    public function test_profile_contains_user_workspaces_and_never_exposes_session_credentials(): void
    {
        $member = AdminPanelDatabase::user('member');
        $issued = $member->createToken('api-token-v1.0.0');
        DB::table('sessions')->insert([
            'id' => 'private-browser-session-id', 'user_id' => $member->id,
            'payload' => 'private-browser-payload', 'ip_address' => '127.0.0.1', 'last_activity' => time(),
        ]);
        $this->get(UserResource::getUrl('view', ['record' => $member]))->assertOk()
            ->assertSee('Posting &amp; referrals', false)->assertSee('Tokens &amp; earnings', false)
            ->assertSee('Business &amp; campaigns', false)->assertSee('Manage account')
            ->assertDontSee($issued->plainTextToken)->assertDontSee($issued->accessToken->token)
            ->assertDontSee('private-browser-session-id')->assertDontSee('private-browser-payload');
    }

    public function test_an_otp_for_an_old_phone_cannot_verify_a_changed_phone(): void
    {
        $member = AdminPanelDatabase::user('member');
        $oldPhone = $member->phone;
        $otp = $this->otp($member);
        DB::table('users')->where('id', $member->id)->update(['phone' => '+254711333444']);
        try {
            app(PhoneOtpService::class)->verifySignupOtp($oldPhone, '123456');
            $this->fail('Expected stale phone proof to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('otp', $exception->errors());
        }
        $this->assertNull($member->fresh()->phone_verified_at);
        $this->assertNull($otp->fresh()->verified_at);
    }

    public function test_verification_migration_backfills_missing_flags_and_preserves_existing_flags(): void
    {
        $member = AdminPanelDatabase::user('member');
        $member->forceFill(['phone_verified_at' => now()])->save();
        Schema::table('users', fn ($table) => $table->dropColumn('is_verified'));
        $migration = require database_path('migrations/2026_10_26_100000_add_user_verification_and_account_events.php');
        $migration->up();
        $this->assertTrue($member->fresh()->is_verified);
        $member->update(['is_verified' => false]);
        $migration->up();
        $this->assertFalse($member->fresh()->is_verified);
        $migration->down();
        $this->assertTrue(Schema::hasTable('user_account_events'));
    }

    public function test_role_and_direct_permissions_can_be_edited_and_bulk_role_removal_works(): void
    {
        $member = AdminPanelDatabase::user('member');
        $owner = AdminPanelDatabase::user('campaign_owner');
        $permission = Permission::create(['name' => 'Advert operations', 'slug' => 'advert_operations', 'category' => 'Campaigns']);
        Livewire::test(EditUser::class, ['record' => $member->id])->fillForm([
            'role_id' => $owner->role_id, 'permissions' => [$permission->id],
        ])->call('save')->assertHasNoFormErrors();
        $member->refresh();
        $this->assertSame($owner->role_id, $member->role_id);
        $this->assertSame([$permission->id], $member->permissions()->pluck('permissions.id')->all());
        $event = UserAccountEvent::where('user_id', $member->id)->firstOrFail();
        $this->assertContains('role_id', $event->metadata['changed_fields']);
        $this->assertContains('permissions', $event->metadata['changed_fields']);
        Livewire::test(ListUsers::class)->callTableBulkAction('assign_role', [$member], ['role_id' => null])
            ->assertHasNoTableBulkActionErrors();
        $this->assertNull($member->fresh()->role_id);
        $this->assertSame(1, $member->permissions()->count());
    }

    public function test_profile_header_verification_and_bulk_account_verification_work(): void
    {
        $first = AdminPanelDatabase::user('member');
        $second = AdminPanelDatabase::user('member');
        Livewire::test(\App\Filament\Resources\UserResource\Pages\ViewUser::class, ['record' => $first->id])
            ->callAction('verify_account', ['reason' => 'Reviewed profile documents'])
            ->assertHasNoActionErrors();
        $this->assertTrue($first->fresh()->is_verified);
        Livewire::test(ListUsers::class)->callTableBulkAction('verify', [$second], ['reason' => 'Batch of reviewed accounts'])
            ->assertHasNoTableBulkActionErrors();
        $this->assertTrue($second->fresh()->is_verified);
        $this->assertNull($second->fresh()->phone_verified_at);
    }

    public function test_user_history_tabs_render_in_their_own_relation_context(): void
    {
        $member = AdminPanelDatabase::user('member');
        $member->createToken('api-token-v1.0.0');
        foreach (UserResource::getRelations() as $relation) {
            Livewire::test($relation, [
                'ownerRecord' => $member, 'pageClass' => \App\Filament\Resources\UserResource\Pages\ViewUser::class,
            ])->assertSuccessful();
        }
    }

    public function test_individual_api_session_revocation_is_scoped_to_the_selected_user(): void
    {
        $member = AdminPanelDatabase::user('member');
        $other = AdminPanelDatabase::user('member');
        $memberToken = $member->createToken('member-app');
        $otherToken = $other->createToken('other-app');
        try {
            app(AdminUserService::class)->revokeApiToken($member, (string) $otherToken->accessToken->id);
            $this->fail('Expected a token belonging to another user to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('token', $exception->errors());
        }
        Livewire::test(UserResource\RelationManagers\ApiSessionsRelationManager::class, [
            'ownerRecord' => $member, 'pageClass' => UserResource\Pages\ViewUser::class,
        ])->callTableAction('revoke', $memberToken->accessToken)->assertHasNoTableActionErrors();
        $this->assertSame(0, $member->tokens()->count());
        $this->assertSame(1, $other->tokens()->count());
        $this->assertDatabaseHas('user_account_events', ['user_id' => $member->id, 'action' => 'api_session_revoked']);
    }

    private function otp(User $member): PhoneVerificationOtp
    {
        return PhoneVerificationOtp::create([
            'user_id' => $member->id, 'phone' => $member->phone, 'purpose' => 'signup',
            'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(10), 'sent_at' => now(),
        ]);
    }
}
