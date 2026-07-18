<?php

namespace Tests\Feature\Jobs;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * INFRA-12 — spec AC P3.1: a nightly export writes the corpus to object
 * storage in a portable, provider-independent format.
 */
class ExportCorpusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    public function test_it_writes_one_ndjson_line_per_draw_under_todays_export_directory(): void
    {
        $this->seedDraw(1);
        $this->seedDraw(500);
        $this->seedDraw(2608);

        ExportCorpus::dispatchSync();

        $path = 'exports/'.now()->format('Y-m-d').'/draws.ndjson';
        Storage::disk('backups')->assertExists($path);

        $this->assertCount(3, $this->lines($path));
    }

    public function test_every_line_is_independently_valid_json_and_reconstructs_the_row(): void
    {
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        $records = $this->records();
        $this->assertCount(1, $records);

        $record = $records[0];
        $draw = Draw::firstOrFail();

        $this->assertSame($draw->id, $record['id']);
        $this->assertSame(GamesEnum::MEGA_SENA->value, $record['type']);
        $this->assertSame(500, $record['draw_number']);
        $this->assertSame($this->payload(500), $record['raw_data']);
    }

    /**
     * A NUL-bearing payload from the real corpus survives the export as valid
     * JSON — the artifact must be parseable, not merely written.
     */
    public function test_a_nul_bearing_payload_exports_as_valid_json(): void
    {
        $this->assertStringContainsString("\0", $this->payload(2194)['nomeTimeCoracaoMesSorte']);
        $this->seedDraw(2194);

        ExportCorpus::dispatchSync();

        $record = $this->records()[0];

        $this->assertSame('', $record['raw_data']['nomeTimeCoracaoMesSorte']);
        $this->assertSame($this->payload(2194)['listaDezenas'], $record['raw_data']['listaDezenas']);
    }

    /**
     * The corpus is read in bounded batches rather than as one unbounded
     * SELECT. That distinction is what keeps memory flat as `draws` grows past
     * the 2,600 rows already seeded, and a `limit` on the read query is its
     * observable signature.
     */
    public function test_it_reads_draws_in_bounded_batches_rather_than_all_at_once(): void
    {
        $this->seedDraw(1);
        $this->seedDraw(500);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        ExportCorpus::dispatchSync();

        $reads = array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'from "draws"'),
        ));

        $this->assertNotEmpty($reads);

        foreach ($reads as $sql) {
            $this->assertStringContainsString('limit', $sql, "Unbounded read of draws: {$sql}");
        }
    }

    public function test_the_export_does_not_mutate_any_draw(): void
    {
        $this->seedDraw(500);
        $this->seedDraw(2194);
        $before = Draw::orderBy('id')->get()->map->getAttributes()->all();

        ExportCorpus::dispatchSync();

        $this->assertSame(2, Draw::count());
        $this->assertEquals($before, Draw::orderBy('id')->get()->map->getAttributes()->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $concurso): array
    {
        $path = database_path("seeders/lotteries/megasena/draws/{$concurso}.json");
        $this->assertFileExists($path);

        return json_decode(file_get_contents($path), true);
    }

    private function seedDraw(int $concurso): Draw
    {
        return Draw::create([
            'type' => GamesEnum::MEGA_SENA,
            'draw_number' => $concurso,
            'draw_date' => '2022-01-01',
            'raw_data' => $this->payload($concurso),
        ]);
    }

    /**
     * @return list<string>
     */
    private function lines(?string $path = null): array
    {
        $path ??= 'exports/'.now()->format('Y-m-d').'/draws.ndjson';
        $contents = Storage::disk('backups')->get($path);

        return array_values(array_filter(explode("\n", $contents), fn (string $l): bool => $l !== ''));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(?string $path = null): array
    {
        return array_map(
            fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            $this->lines($path),
        );
    }
}
