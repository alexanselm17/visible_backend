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
class AddTokenUsageToAdvertSubmissionsMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('token_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
        });
    }

    public function test_it_creates_type_and_token_usage_columns_on_a_fresh_schema(): void
    {
        $this->createAdvertSubmissionsTable();

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumns('advert_submissions', [
            'type',
            'token_type_id',
            'tokens_reserved',
            'tokens_spent',
            'video_duration_seconds',
            'final_video_duration_seconds',
            'tokens_refunded_at',
        ]));
        $this->assertTokenTypeForeignKeyExists();
    }

    public function test_it_preserves_an_existing_type_column_and_its_values(): void
    {
        $this->createAdvertSubmissionsTable(withType: true);

        DB::table('advert_submissions')->insert([
            'id' => 'fa7db721-338b-47b2-8709-920f2156471b',
            'target_audience' => null,
            'type' => 'VIDEO',
        ]);

        $this->migration()->up();

        $this->assertSame('VIDEO', DB::table('advert_submissions')->value('type'));
        $this->assertTrue(Schema::hasColumn('advert_submissions', 'token_type_id'));
        $this->assertTokenTypeForeignKeyExists();
    }

    private function createAdvertSubmissionsTable(bool $withType = false): void
    {
        Schema::create('advert_submissions', function (Blueprint $table) use ($withType) {
            $table->uuid('id')->primary();
            $table->json('target_audience')->nullable();

            if ($withType) {
                $table->string('type', 20)->nullable();
            }
        });
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_08_13_090100_add_token_usage_to_advert_submissions.php');
    }

    private function assertTokenTypeForeignKeyExists(): void
    {
        $this->assertTrue(
            collect(Schema::getForeignKeys('advert_submissions'))->contains(
                fn (array $foreignKey) => $foreignKey['columns'] === ['token_type_id']
                    && $foreignKey['foreign_table'] === 'token_types'
                    && $foreignKey['foreign_columns'] === ['id']
            )
        );
    }
}
