<?php

namespace Tests\Feature\Jobs\ExportCorpusTest;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
});

test('it writes one ndjson line per draw under todays export directory', function () {
    seedDraw(1);
    seedDraw(500);
    seedDraw(2608);

    ExportCorpus::dispatchSync();

    $path = 'exports/'.now()->format('Y-m-d').'/draws.ndjson';
    Storage::disk('backups')->assertExists($path);

    expect(lines($path))->toHaveCount(3);
});

test('every line is independently valid json and reconstructs the row', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    $records = records();
    expect($records)->toHaveCount(1);

    $record = $records[0];
    $draw = Draw::firstOrFail();

    expect($record['id'])->toBe($draw->id);
    expect($record['type'])->toBe(GamesEnum::MEGA_SENA->value);
    expect($record['draw_number'])->toBe(500);
    expect($record['raw_data'])->toBe(payload(500));
});

test('a nul bearing payload exports as valid json', function () {
    $this->assertStringContainsString("\0", payload(2194)['nomeTimeCoracaoMesSorte']);
    seedDraw(2194);

    ExportCorpus::dispatchSync();

    $record = records()[0];

    expect($record['raw_data']['nomeTimeCoracaoMesSorte'])->toBe('');
    expect($record['raw_data']['listaDezenas'])->toBe(payload(2194)['listaDezenas']);
});

test('it reads draws in bounded batches rather than all at once', function () {
    seedDraw(1);
    seedDraw(500);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    ExportCorpus::dispatchSync();

    $reads = array_values(array_filter(
        $queries,
        fn (string $sql): bool => str_contains($sql, 'from "draws"'),
    ));

    expect($reads)->not->toBeEmpty();

    foreach ($reads as $sql) {
        $this->assertStringContainsString('limit', $sql, "Unbounded read of draws: {$sql}");
    }
});

test('the export does not mutate any draw', function () {
    seedDraw(500);
    seedDraw(2194);
    $before = Draw::orderBy('id')->get()->map->getAttributes()->all();

    ExportCorpus::dispatchSync();

    expect(Draw::count())->toBe(2);
    expect(Draw::orderBy('id')->get()->map->getAttributes()->all())->toEqual($before);
});

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

/**
 * @return list<string>
 */
function lines(?string $path = null): array
{
    $path ??= 'exports/'.now()->format('Y-m-d').'/draws.ndjson';
    $contents = Storage::disk('backups')->get($path);

    return array_values(array_filter(explode("\n", $contents), fn (string $l): bool => $l !== ''));
}

/**
 * @return list<array<string, mixed>>
 */
function records(?string $path = null): array
{
    return array_map(
        fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR),
        lines($path),
    );
}
