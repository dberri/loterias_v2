<?php

namespace Tests\Feature\Commands\RestoreCorpusTest;

use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\PageAssembler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * INFRA-15 — spec AC P3.4: "WHEN a restore is performed from an export artifact
 * into an empty database THEN draws and pages SHALL be fully reconstructed."
 *
 * Every test here restores from artifacts produced by the real export rather
 * than from hand-built fixtures, so the two halves of the backup are proven to
 * fit together rather than assumed to.
 */
uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
});

test('it reconstructs both tables from an export artifact', function () {
    seedDraw(500);
    seedDraw(2194);
    Page::factory()->count(3)->create();

    exportThenEmptyTheDatabase();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->assertExitCode(0);

    expect(Draw::count())->toBe(2);
    expect(Page::count())->toBe(3);
});

test('restored draws match the originals including their payloads', function () {
    seedDraw(500);
    seedDraw(2194);
    $before = Draw::orderBy('id')->get()->map->getAttributes()->all();

    exportThenEmptyTheDatabase();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->assertExitCode(0);

    expect(Draw::orderBy('id')->get()->map->getAttributes()->all())->toEqual($before);
    expect(Draw::where('draw_number', 500)->firstOrFail()->raw_data['listaDezenas'])->toBe(payload(500)['listaDezenas']);
});

test('restored pages keep their nested blocks and status', function () {
    $blocks = [
        ['type' => 'hero-section', 'data' => ['title' => 'Resultado', 'numbers' => ['05', '16']]],
        ['type' => 'faq', 'data' => ['items' => [['question' => 'Acumulou?', 'answer' => 'Não']]]],
    ];
    $page = Page::factory()->published()->create(['blocks' => $blocks]);
    $slug = $page->slug;

    exportThenEmptyTheDatabase();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->assertExitCode(0);

    $restored = Page::where('slug', $slug)->firstOrFail();

    expect($restored->blocks)->toBe($blocks);
    expect($restored->status)->toBe($page->status);
});

/**
 * The refusal that matters most: a corrupt artifact must be rejected before
 * anything is written, so the operator falls back to another backup instead
 * of ending up with a half-restored database that looks recovered.
 */
test('it refuses a corrupt artifact without importing anything', function () {
    seedDraw(500);
    Page::factory()->create();

    exportThenEmptyTheDatabase();

    Storage::disk('backups')->put(
        directory().'/draws.ndjson',
        Storage::disk('backups')->get(directory().'/draws.ndjson').'{"id":999}'."\n",
    );

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->expectsOutputToContain('failed checksum verification')
        ->assertExitCode(1);

    expect(Draw::count())->toBe(0);
    expect(Page::count())->toBe(0);
});

test('it fails when the restored row count disagrees with the manifest', function () {
    seedDraw(500);

    exportThenEmptyTheDatabase();

    $manifest = json_decode(Storage::disk('backups')->get(directory().'/manifest.json'), true);
    $manifest['tables']['draws']['rows'] = 99;
    Storage::disk('backups')->put(
        directory().'/manifest.json',
        (string) json_encode($manifest),
    );

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->expectsOutputToContain('Row-count mismatch')
        ->assertExitCode(1);
});

test('it refuses to restore into a database that is not empty', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->expectsOutputToContain('is not empty')
        ->assertExitCode(1);

    expect(Draw::count())->toBe(1);
});

test('it refuses an artifact directory without a manifest', function () {
    seedDraw(500);
    exportThenEmptyTheDatabase();

    Storage::disk('backups')->delete(directory().'/manifest.json');

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->expectsOutputToContain('No manifest found')
        ->assertExitCode(1);

    expect(Draw::count())->toBe(0);
});

/**
 * Rows are restored with their original identifiers. A database whose next
 * insert collides on the primary key has not been fully reconstructed.
 */
test('new rows can still be written after a restore', function () {
    seedDraw(500);
    Page::factory()->create();

    exportThenEmptyTheDatabase();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->assertExitCode(0);

    $draw = seedDraw(2608);
    $page = Page::factory()->create();

    expect($draw->exists)->toBeTrue();
    expect($page->exists)->toBeTrue();
    expect(Draw::count())->toBe(2);
    expect(Page::count())->toBe(2);
});

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
test('a previously published page still renders after restore', function () {
    $draw = seedDraw(2608);
    $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'megasena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [],
    ]));
    $page->update(['status' => PageStatus::Published->value]);

    $this->get('/megasena/resultado/2608')->assertOk();

    exportThenEmptyTheDatabase();
    $this->get('/megasena/resultado/2608')->assertNotFound();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->assertExitCode(0);

    $this->get('/megasena/resultado/2608')->assertOk();
});

/**
 * The publish gate must survive the restore too — a restore that silently
 * promoted every page to Published would pass a naive "it renders" check
 * while exposing unreviewed AI output (AD-006).
 */
test('restore preserves the publish gate for unpublished pages', function () {
    $draw = seedDraw(2608);
    $page = (new PageAssembler)->assemble($draw, GenerationResult::valid('page_megasena_2608', [
        'title' => 'Resultado Mega-Sena concurso 2608',
        'slug' => 'megasena/resultado/2608',
        'meta_description' => 'Resumo do concurso 2608',
        'enrichment_blocks' => [],
    ]));
    $page->update(['status' => PageStatus::Generated->value]);

    exportThenEmptyTheDatabase();

    $this->artisan('app:restore-corpus', ['directory' => directory()])
        ->assertExitCode(0);

    expect(Page::query()->firstOrFail()->status)->toBe(PageStatus::Generated);
    $this->get('/megasena/resultado/2608')->assertNotFound();
});

function directory(): string
{
    return 'exports/'.now()->format('Y-m-d');
}

/**
 * A restore targets a genuinely fresh database, so the identity sequences
 * are rewound too. Deleting rows alone would leave the sequences advanced
 * and hide the primary-key collision a real restore has to survive.
 */
function exportThenEmptyTheDatabase(): void
{
    ExportCorpus::dispatchSync();

    Page::query()->delete();
    Draw::query()->delete();

    foreach (['draws', 'pages'] as $table) {
        DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), 1, false)");
    }

    expect(Draw::count())->toBe(0);
    expect(Page::count())->toBe(0);
}

/**
 * @return array<string, mixed>
 */
function payload(int $concurso): array
{
    $path = database_path("seeders/lotteries/megasena/draws/{$concurso}.json");
    expect($path)->toBeFile();

    return json_decode(file_get_contents($path), true);
}

function seedDraw(int $concurso): Draw
{
    return Draw::create([
        'type' => GamesEnum::MEGA_SENA,
        'draw_number' => $concurso,
        'draw_date' => '2022-01-01',
        'raw_data' => payload($concurso),
    ]);
}
