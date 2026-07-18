<?php

namespace Tests\Feature\Commands;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\PageAssembler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * INFRA-15 — spec AC P3.4: "WHEN a restore is performed from an export artifact
 * into an empty database THEN draws and pages SHALL be fully reconstructed."
 *
 * Every test here restores from artifacts produced by the real export rather
 * than from hand-built fixtures, so the two halves of the backup are proven to
 * fit together rather than assumed to.
 */
class RestoreCorpusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    public function test_it_reconstructs_both_tables_from_an_export_artifact(): void
    {
        $this->seedDraw(500);
        $this->seedDraw(2194);
        Page::factory()->count(3)->create();

        $this->exportThenEmptyTheDatabase();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->assertExitCode(0);

        $this->assertSame(2, Draw::count());
        $this->assertSame(3, Page::count());
    }

    public function test_restored_draws_match_the_originals_including_their_payloads(): void
    {
        $this->seedDraw(500);
        $this->seedDraw(2194);
        $before = Draw::orderBy('id')->get()->map->getAttributes()->all();

        $this->exportThenEmptyTheDatabase();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->assertExitCode(0);

        $this->assertEquals($before, Draw::orderBy('id')->get()->map->getAttributes()->all());
        $this->assertSame(
            $this->payload(500)['listaDezenas'],
            Draw::where('draw_number', 500)->firstOrFail()->raw_data['listaDezenas'],
        );
    }

    public function test_restored_pages_keep_their_nested_blocks_and_status(): void
    {
        $blocks = [
            ['type' => 'hero-section', 'data' => ['title' => 'Resultado', 'numbers' => ['05', '16']]],
            ['type' => 'faq', 'data' => ['items' => [['question' => 'Acumulou?', 'answer' => 'Não']]]],
        ];
        $page = Page::factory()->published()->create(['blocks' => $blocks]);
        $slug = $page->slug;

        $this->exportThenEmptyTheDatabase();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->assertExitCode(0);

        $restored = Page::where('slug', $slug)->firstOrFail();

        $this->assertSame($blocks, $restored->blocks);
        $this->assertSame($page->status, $restored->status);
    }

    /**
     * The refusal that matters most: a corrupt artifact must be rejected before
     * anything is written, so the operator falls back to another backup instead
     * of ending up with a half-restored database that looks recovered.
     */
    public function test_it_refuses_a_corrupt_artifact_without_importing_anything(): void
    {
        $this->seedDraw(500);
        Page::factory()->create();

        $this->exportThenEmptyTheDatabase();

        Storage::disk('backups')->put(
            $this->directory().'/draws.ndjson',
            Storage::disk('backups')->get($this->directory().'/draws.ndjson').'{"id":999}'."\n",
        );

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->expectsOutputToContain('failed checksum verification')
            ->assertExitCode(1);

        $this->assertSame(0, Draw::count());
        $this->assertSame(0, Page::count());
    }

    public function test_it_fails_when_the_restored_row_count_disagrees_with_the_manifest(): void
    {
        $this->seedDraw(500);

        $this->exportThenEmptyTheDatabase();

        $manifest = json_decode(Storage::disk('backups')->get($this->directory().'/manifest.json'), true);
        $manifest['tables']['draws']['rows'] = 99;
        Storage::disk('backups')->put(
            $this->directory().'/manifest.json',
            (string) json_encode($manifest),
        );

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->expectsOutputToContain('Row-count mismatch')
            ->assertExitCode(1);
    }

    public function test_it_refuses_to_restore_into_a_database_that_is_not_empty(): void
    {
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->expectsOutputToContain('is not empty')
            ->assertExitCode(1);

        $this->assertSame(1, Draw::count());
    }

    public function test_it_refuses_an_artifact_directory_without_a_manifest(): void
    {
        $this->seedDraw(500);
        $this->exportThenEmptyTheDatabase();

        Storage::disk('backups')->delete($this->directory().'/manifest.json');

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->expectsOutputToContain('No manifest found')
            ->assertExitCode(1);

        $this->assertSame(0, Draw::count());
    }

    /**
     * Rows are restored with their original identifiers. A database whose next
     * insert collides on the primary key has not been fully reconstructed.
     */
    public function test_new_rows_can_still_be_written_after_a_restore(): void
    {
        $this->seedDraw(500);
        Page::factory()->create();

        $this->exportThenEmptyTheDatabase();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->assertExitCode(0);

        $draw = $this->seedDraw(2608);
        $page = Page::factory()->create();

        $this->assertTrue($draw->exists);
        $this->assertTrue($page->exists);
        $this->assertSame(2, Draw::count());
        $this->assertSame(2, Page::count());
    }

    /**
     * INFRA-15 — spec AC P3.4 requires more than row reconstruction: "a sampled
     * set of previously-Published draw pages SHALL render correctly afterward."
     *
     * Row counts and column equality cannot show that, because a page can be
     * restored intact and still fail to serve if the draw relation, the status,
     * or the slug does not survive the round trip. This asserts the restored
     * corpus is actually servable, which is the only property the operator cares
     * about at 3am.
     */
    public function test_a_previously_published_page_still_renders_after_restore(): void
    {
        $draw = $this->seedDraw(2608);
        $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [],
        ]));
        $page->update(['status' => PageStatus::Published->value]);

        $this->get('/megasena/resultado/2608')->assertOk();

        $this->exportThenEmptyTheDatabase();
        $this->get('/megasena/resultado/2608')->assertNotFound();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->assertExitCode(0);

        $this->get('/megasena/resultado/2608')->assertOk();
    }

    /**
     * The publish gate must survive the restore too — a restore that silently
     * promoted every page to Published would pass a naive "it renders" check
     * while exposing unreviewed AI output (AD-006).
     */
    public function test_restore_preserves_the_publish_gate_for_unpublished_pages(): void
    {
        $draw = $this->seedDraw(2608);
        $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
            'title' => 'Resultado Mega-Sena concurso 2608',
            'slug' => 'megasena/resultado/2608',
            'meta_description' => 'Resumo do concurso 2608',
            'enrichment_blocks' => [],
        ]));
        $page->update(['status' => PageStatus::Generated->value]);

        $this->exportThenEmptyTheDatabase();

        $this->artisan('app:restore-corpus', ['directory' => $this->directory()])
            ->assertExitCode(0);

        $this->assertSame(PageStatus::Generated, Page::query()->firstOrFail()->status);
        $this->get('/megasena/resultado/2608')->assertNotFound();
    }

    private function directory(): string
    {
        return 'exports/'.now()->format('Y-m-d');
    }

    /**
     * A restore targets a genuinely fresh database, so the identity sequences
     * are rewound too. Deleting rows alone would leave the sequences advanced
     * and hide the primary-key collision a real restore has to survive.
     */
    private function exportThenEmptyTheDatabase(): void
    {
        ExportCorpus::dispatchSync();

        Page::query()->delete();
        Draw::query()->delete();

        foreach (['draws', 'pages'] as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
        }

        $this->assertSame(0, Draw::count());
        $this->assertSame(0, Page::count());
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
}
