<?php

namespace Tests\Feature\Jobs\ExportCorpusRetentionTest;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
});

test('an export is promoted into the monthly tier', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists(monthly('draws.ndjson'));
    Storage::disk('backups')->assertExists(monthly('manifest.json'));
});

test('the monthly tier is a sibling of the daily prefix not nested inside it', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    $this->assertStringStartsNotWith('exports/', monthly('manifest.json'));

    foreach (Storage::disk('backups')->allFiles('monthly') as $path) {
        $this->assertStringStartsNotWith('exports/', $path);
    }
});

test('a later export in the same month does not replace the monthly artifact', function () {
    $this->travelTo('2026-03-04 03:30:00');
    seedDraw(500);
    ExportCorpus::dispatchSync();

    $first = Storage::disk('backups')->get(monthly('draws.ndjson'));

    $this->travelTo('2026-03-19 03:30:00');
    seedDraw(2194);
    ExportCorpus::dispatchSync();

    expect(Storage::disk('backups')->get(monthly('draws.ndjson')))->toBe($first);
    $this->assertStringNotContainsString('"draw_number":2194', $first);
});

test('each month gets its own artifact', function () {
    $this->travelTo('2026-03-19 03:30:00');
    seedDraw(500);
    ExportCorpus::dispatchSync();

    $this->travelTo('2026-04-02 03:30:00');
    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists('monthly/2026-03/manifest.json');
    Storage::disk('backups')->assertExists('monthly/2026-04/manifest.json');
});

test('a month whose first nights were missed is still promoted later', function () {
    $this->travelTo('2026-03-17 03:30:00');
    seedDraw(500);

    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists('monthly/2026-03/manifest.json');
});

test('the monthly artifact matches the checksum its manifest records', function () {
    seedDraw(500);
    seedDraw(2194);

    ExportCorpus::dispatchSync();

    $manifest = json_decode(
        Storage::disk('backups')->get(monthly('manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    foreach ($manifest['tables'] as $meta) {
        expect(hash('sha256', Storage::disk('backups')->get(monthly($meta['artifact']))))->toBe($meta['sha256']);
    }
});

test('the monthly artifact holds the same bytes as the daily it was promoted from', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    expect(Storage::disk('backups')->get(monthly('draws.ndjson')))->toBe(Storage::disk('backups')->get('exports/'.now()->format('Y-m-d').'/draws.ndjson'));
});

function monthly(string $artifact): string
{
    return 'monthly/'.now()->format('Y-m').'/'.$artifact;
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
