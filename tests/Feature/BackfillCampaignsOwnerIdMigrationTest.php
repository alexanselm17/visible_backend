<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * @preserveGlobalState disabled
 *
 * @runTestsInSeparateProcesses
 */
class BackfillCampaignsOwnerIdMigrationTest extends TestCase
{
    private const DEFAULT_OWNER_ID = '3acf6e5a-591d-40ea-bf4a-230190bd6795';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });

        DB::table('users')->insert(['id' => self::DEFAULT_OWNER_ID]);
    }

    public function test_it_creates_owner_id_on_a_fresh_campaigns_table(): void
    {
        $this->createCampaignsTable();

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('campaigns', 'owner_id'));
        $this->assertFalse($this->ownerIdColumn()['nullable']);
        $this->assertOwnerForeignKeyExists();
    }

    public function test_it_preserves_the_existing_owner_id_backfill_behavior(): void
    {
        $this->createCampaignsTable(withOwnerId: true);

        DB::table('campaigns')->insert([
            ['id' => '4b10c33f-69c0-4b36-8aa1-b00a74d5f657', 'name' => 'Null owner', 'owner_id' => null],
            ['id' => 'fa7db721-338b-47b2-8709-920f2156471b', 'name' => 'Empty owner', 'owner_id' => ''],
        ]);

        $this->migration()->up();

        $this->assertSame(
            [self::DEFAULT_OWNER_ID, self::DEFAULT_OWNER_ID],
            DB::table('campaigns')->orderBy('name')->pluck('owner_id')->all()
        );
        $this->assertFalse($this->ownerIdColumn()['nullable']);
        $this->assertOwnerForeignKeyExists();
    }

    private function createCampaignsTable(bool $withOwnerId = false): void
    {
        Schema::create('campaigns', function (Blueprint $table) use ($withOwnerId) {
            $table->uuid('id')->primary();
            $table->string('name');

            if ($withOwnerId) {
                $table->uuid('owner_id')->nullable();
            }

            $table->timestamps();
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_01_26_131954_backfill_campaigns_owner_id.php');
    }

    private function ownerIdColumn(): array
    {
        return collect(Schema::getColumns('campaigns'))
            ->firstWhere('name', 'owner_id');
    }

    private function assertOwnerForeignKeyExists(): void
    {
        $this->assertTrue(
            collect(Schema::getForeignKeys('campaigns'))->contains(
                fn (array $foreignKey) => $foreignKey['columns'] === ['owner_id']
                    && $foreignKey['foreign_table'] === 'users'
                    && $foreignKey['foreign_columns'] === ['id']
            )
        );
    }
}
