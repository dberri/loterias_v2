<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ExportCorpus;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * INFRA-12 / INFRA-18 / INFRA-19 — the pages slice of the export and the two
 * independent-deployability edge cases from the spec.
 */
class ExportCorpusPagesTest extends TestCase
{
    use RefreshDatabase;

    private const DRAWS = 'draws.ndjson';

    private const PAGES = 'pages.ndjson';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    public function test_it_exports_pages_alongside_draws(): void
    {
        Page::factory()->count(3)->create();

        ExportCorpus::dispatchSync();

        Storage::disk('backups')->assertExists($this->path(self::DRAWS));
        Storage::disk('backups')->assertExists($this->path(self::PAGES));
        $this->assertCount(3, $this->lines(self::PAGES));
    }

    public function test_every_pages_line_reconstructs_the_row_with_nested_blocks_intact(): void
    {
        $blocks = [
            [
                'type' => 'hero-section',
                'data' => [
                    'title' => 'Resultado da Mega-Sena 2500',
                    'numbers' => ['05', '16', '25', '32', '39', '55'],
                    'meta' => ['accumulated' => false, 'prize' => 37000000.55],
                ],
            ],
            ['type' => 'faq', 'data' => ['items' => [['question' => 'Acumulou?', 'answer' => 'Não']]]],
        ];

        $page = Page::factory()->published()->create(['blocks' => $blocks]);

        ExportCorpus::dispatchSync();

        $record = $this->records(self::PAGES)[0];

        $this->assertSame($page->id, $record['id']);
        $this->assertSame($page->slug, $record['slug']);
        $this->assertSame($page->status->value, $record['status']);
        $this->assertSame($blocks, $record['blocks']);
    }

    /**
     * INFRA-18. `pages` belongs to a soft dependency, so an export running
     * before that feature ships must still produce a usable backup of draws.
     */
    public function test_it_exports_draws_alone_and_succeeds_when_the_pages_table_is_absent(): void
    {
        Schema::drop((new Page)->getTable());
        $this->assertFalse(Schema::hasTable((new Page)->getTable()));

        ExportCorpus::dispatchSync();

        Storage::disk('backups')->assertExists($this->path(self::DRAWS));
        Storage::disk('backups')->assertMissing($this->path(self::PAGES));
    }

    /**
     * INFRA-19. An empty table yields a present, well-formed, zero-row file —
     * never a skipped one, so "nothing to back up" stays distinguishable from
     * "the backup did not run".
     */
    public function test_an_empty_pages_table_produces_a_present_but_zero_row_artifact(): void
    {
        $this->assertSame(0, Page::count());

        ExportCorpus::dispatchSync();

        Storage::disk('backups')->assertExists($this->path(self::PAGES));
        $this->assertSame('', Storage::disk('backups')->get($this->path(self::PAGES)));
        $this->assertCount(0, $this->lines(self::PAGES));
    }

    public function test_it_reads_pages_in_bounded_batches_rather_than_all_at_once(): void
    {
        Page::factory()->count(2)->create();

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        ExportCorpus::dispatchSync();

        $reads = array_values(array_filter(
            $queries,
            fn (string $sql): bool => str_contains($sql, 'from "pages"'),
        ));

        $this->assertNotEmpty($reads);

        foreach ($reads as $sql) {
            $this->assertStringContainsString('limit', $sql, "Unbounded read of pages: {$sql}");
        }
    }

    public function test_the_export_does_not_mutate_any_page(): void
    {
        Page::factory()->count(2)->create();
        $before = Page::orderBy('id')->get()->map->getAttributes()->all();

        ExportCorpus::dispatchSync();

        $this->assertSame(2, Page::count());
        $this->assertEquals($before, Page::orderBy('id')->get()->map->getAttributes()->all());
    }

    private function path(string $artifact): string
    {
        return 'exports/'.now()->format('Y-m-d').'/'.$artifact;
    }

    /**
     * @return list<string>
     */
    private function lines(string $artifact): array
    {
        $contents = Storage::disk('backups')->get($this->path($artifact));

        return array_values(array_filter(explode("\n", $contents), fn (string $l): bool => $l !== ''));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(string $artifact): array
    {
        return array_map(
            fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
            $this->lines($artifact),
        );
    }
}
