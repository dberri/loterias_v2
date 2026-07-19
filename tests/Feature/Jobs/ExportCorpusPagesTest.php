<?php

namespace Tests\Feature\Jobs\ExportCorpusPagesTest;

use App\Jobs\ExportCorpus;
use App\Models\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

const DRAWS = 'draws.ndjson';

const PAGES = 'pages.ndjson';

beforeEach(function () {
    Storage::fake('backups');
});

test('it exports pages alongside draws', function () {
    Page::factory()->count(3)->create();

    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists(path(DRAWS));
    Storage::disk('backups')->assertExists(path(PAGES));
    expect(lines(PAGES))->toHaveCount(3);
});

test('every pages line reconstructs the row with nested blocks intact', function () {
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

    $record = records(PAGES)[0];

    expect($record['id'])->toBe($page->id);
    expect($record['slug'])->toBe($page->slug);
    expect($record['status'])->toBe($page->status->value);
    expect($record['blocks'])->toBe($blocks);
});

test('it exports draws alone and succeeds when the pages table is absent', function () {
    Schema::drop((new Page)->getTable());
    expect(Schema::hasTable((new Page)->getTable()))->toBeFalse();

    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists(path(DRAWS));
    Storage::disk('backups')->assertMissing(path(PAGES));
});

test('an empty pages table produces a present but zero row artifact', function () {
    expect(Page::count())->toBe(0);

    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists(path(PAGES));
    expect(Storage::disk('backups')->get(path(PAGES)))->toBe('');
    expect(lines(PAGES))->toHaveCount(0);
});

test('it reads pages in bounded batches rather than all at once', function () {
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

    expect($reads)->not->toBeEmpty();

    foreach ($reads as $sql) {
        $this->assertStringContainsString('limit', $sql, "Unbounded read of pages: {$sql}");
    }
});

test('the export does not mutate any page', function () {
    Page::factory()->count(2)->create();
    $before = Page::orderBy('id')->get()->map->getAttributes()->all();

    ExportCorpus::dispatchSync();

    expect(Page::count())->toBe(2);
    expect(Page::orderBy('id')->get()->map->getAttributes()->all())->toEqual($before);
});

function path(string $artifact): string
{
    return 'exports/'.now()->format('Y-m-d').'/'.$artifact;
}

/**
 * @return list<string>
 */
function lines(string $artifact): array
{
    $contents = Storage::disk('backups')->get(path($artifact));

    return array_values(array_filter(explode("\n", $contents), fn (string $l): bool => $l !== ''));
}

/**
 * @return list<array<string, mixed>>
 */
function records(string $artifact): array
{
    return array_map(
        fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        lines($artifact),
    );
}
