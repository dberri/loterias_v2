<?php

namespace Tests\Feature\Database;

use App\Enums\GamesEnum;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * INFRA-08 — covers the one migration whose behaviour `migrate:fresh` cannot
 * prove.
 *
 * add_draw_date_to_draws_table does two things a schema-only migration does
 * not: it BACKFILLS draw_date by reading each row's raw_data, and it then
 * tightens the column to NOT NULL via ->change(). Running it against an empty
 * database exercises neither — the backfill loop has nothing to iterate and the
 * constraint has nothing to reject. It is also the only migration in the
 * project that touches raw `DB::` (see dialect-audit.md), which makes it the
 * most likely place for a Postgres dialect difference to hide.
 *
 * The migration object is driven directly rather than through
 * `migrate:rollback --step=N`, so the test does not silently stop covering
 * anything the moment another migration is added after it.
 */
class DrawDateBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_draw_date_from_raw_data_and_then_enforces_not_null(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->insertWithoutDrawDate(500, '11/03/2003');
        $this->insertWithoutDrawDate(2194, '30/12/2019');

        $migration->up();

        $this->assertSame('2003-03-11', $this->drawDateOf(500));
        $this->assertSame('2019-12-30', $this->drawDateOf(2194));

        $this->assertSame('NO', DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns
             WHERE table_name = 'draws' AND column_name = 'draw_date'"
        )->is_nullable);
    }

    /**
     * The backfill reads dataApuracao out of a json column. On Postgres the
     * raw query builder hands that column back as a string rather than an
     * array, and a decode that assumed otherwise would leave every draw_date
     * null — which the NOT NULL step would then turn into a failed migration.
     */
    public function test_the_backfill_decodes_raw_data_correctly_on_postgres(): void
    {
        $migration = $this->migration();
        $migration->down();

        $this->insertWithoutDrawDate(500, '11/03/2003');

        $migration->up();

        $this->assertNotNull($this->drawDateOf(500));
    }

    public function test_the_tightened_column_rejects_a_draw_with_no_date(): void
    {
        $this->expectException(QueryException::class);

        DB::table('draws')->insert([
            'type' => GamesEnum::MEGA_SENA->value,
            'draw_number' => 9001,
            'draw_date' => null,
            'raw_data' => json_encode(['dataApuracao' => '11/03/2003']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2025_08_24_190138_add_draw_date_to_draws_table.php');
    }

    private function drawDateOf(int $drawNumber): ?string
    {
        $value = DB::table('draws')->where('draw_number', $drawNumber)->value('draw_date');

        return $value === null ? null : substr((string) $value, 0, 10);
    }

    private function insertWithoutDrawDate(int $drawNumber, string $dataApuracao): void
    {
        DB::table('draws')->insert([
            'type' => GamesEnum::MEGA_SENA->value,
            'draw_number' => $drawNumber,
            'raw_data' => json_encode(['dataApuracao' => $dataApuracao, 'listaDezenas' => ['01', '02']]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
