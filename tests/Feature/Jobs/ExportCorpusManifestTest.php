<?php

namespace Tests\Feature\Jobs\ExportCorpusManifestTest;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use App\Models\Page;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
});

test('it writes a manifest alongside the artifacts', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    Storage::disk('backups')->assertExists(path('manifest.json'));
    expect(manifest())->toBeArray();
});

test('the manifest records the export timestamp', function () {
    $this->travelTo('2026-07-18 03:15:00');
    seedDraw(500);

    ExportCorpus::dispatchSync();

    expect(manifest()['exported_at'])->toBe(now()->toIso8601String());
});

test('the manifest records a truthful row count for every table', function () {
    seedDraw(1);
    seedDraw(500);
    Page::factory()->count(4)->create();

    ExportCorpus::dispatchSync();

    $manifest = manifest();

    expect($manifest['tables']['draws']['rows'])->toBe(2);
    expect($manifest['tables']['pages']['rows'])->toBe(4);
});

test('an empty table is recorded with a row count of zero', function () {
    seedDraw(500);
    expect(Page::count())->toBe(0);

    ExportCorpus::dispatchSync();

    expect(manifest()['tables']['pages']['rows'])->toBe(0);
});

test('the manifest records a sha256 matching each artifact on disk', function () {
    seedDraw(500);
    Page::factory()->create();

    ExportCorpus::dispatchSync();

    foreach (manifest()['tables'] as $meta) {
        expect($meta['sha256'])->toBe(hash('sha256', Storage::disk('backups')->get(path($meta['artifact']))));
    }
});

test('the manifest records the app and schema version', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    $manifest = manifest();

    $ran = app('migrator')->getRepository()->getRan();

    expect($manifest['app_version'])->toBe((string) config('app.version', app()->version()));
    expect($manifest['app_version'])->not->toBeEmpty();
    expect($manifest['schema_version'])->toBe((string) end($ran));
});

test('the manifest omits a table that does not exist', function () {
    Schema::drop((new Page)->getTable());
    seedDraw(500);

    ExportCorpus::dispatchSync();

    expect(manifest()['tables'])->toHaveKey('draws');
    $this->assertArrayNotHasKey('pages', manifest()['tables']);
});

test('the export fails when the disk returns different bytes than were written', function () {
    seedDraw(500);
    makeBackupDiskCorruptOnRead();

    try {
        ExportCorpus::dispatchSync();
        $this->fail('The export completed despite an artifact that does not match what was written.');
    } catch (RuntimeException $e) {
        $this->assertStringContainsString('failed checksum verification', $e->getMessage());
    }

    Storage::disk('backups')->assertMissing(path('manifest.json'));
});

test('an artifact corrupted after the export fails verification', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    $manifest = manifest();
    Storage::disk('backups')->put(
        path('draws.ndjson'),
        Storage::disk('backups')->get(path('draws.ndjson')).'{"id":999}'."\n",
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessageMatches('/failed checksum verification/');

    (new ExportCorpus)->verifyArtifacts(directory(), $manifest);
});

function directory(): string
{
    return 'exports/'.now()->format('Y-m-d');
}

function path(string $artifact): string
{
    return directory().'/'.$artifact;
}

/**
 * @return array<string, mixed>
 */
function manifest(): array
{
    return json_decode(
        Storage::disk('backups')->get(path('manifest.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

/**
 * Swap the backups disk for one that silently alters every artifact it
 * hands back on read.
 */
function makeBackupDiskCorruptOnRead(): void
{
    $fake = Storage::disk('backups');

    Storage::set('backups', new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter
    {
        public function readStream($path)
        {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, (string) parent::get($path).'corrupted');
            rewind($stream);

            return $stream;
        }
    });
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
