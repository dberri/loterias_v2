<?php

namespace Tests\Feature\Jobs\ExportCorpusAlertingTest;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Mail\OperatorAlert;
use App\Models\Draw;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use Throwable;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('backups');
    Mail::fake();
});

test('a checksum mismatch alerts the operator', function () {
    seedDraw(500);
    makeBackupDiskCorruptOnRead();

    runFailingExport();

    Mail::assertSent(OperatorAlert::class);
});

test('unreachable storage alerts rather than exiting zero', function () {
    seedDraw(500);
    makeBackupDiskUnwritable();

    $threw = false;
    try {
        ExportCorpus::dispatchSync();
    } catch (Throwable) {
        $threw = true;
    }

    expect($threw)->toBeTrue('The export swallowed an unreachable-storage failure and exited zero.');
    Mail::assertSent(OperatorAlert::class);
});

test('the failure is rethrown so the job does not report success', function () {
    seedDraw(500);
    makeBackupDiskCorruptOnRead();

    $this->expectException(Throwable::class);

    ExportCorpus::dispatchSync();
});

test('a failed export lands in the failed jobs table', function () {
    seedDraw(500);
    makeBackupDiskCorruptOnRead();

    config(['queue.default' => 'database']);
    Queue::setDefaultDriver('database');

    ExportCorpus::dispatch();

    $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->run();

    expect((int) DB::table('failed_jobs')->count())->toBe(1);
});

test('repeated nightly failures send only one email', function () {
    seedDraw(500);
    makeBackupDiskCorruptOnRead();

    runFailingExport();
    $this->travel(1)->days();
    runFailingExport();
    $this->travel(1)->days();
    runFailingExport();

    Mail::assertSentCount(1);
});

test('a successful export sends no alert', function () {
    seedDraw(500);

    ExportCorpus::dispatchSync();

    Mail::assertNothingSent();
});

function runFailingExport(): void
{
    try {
        ExportCorpus::dispatchSync();
    } catch (Throwable) {
        // The failure is the point; the assertions are about what it emitted.
    }
}

/**
 * Storage that accepts a write and stores something else.
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
 * Storage that is simply not there.
 */
function makeBackupDiskUnwritable(): void
{
    $fake = Storage::disk('backups');

    Storage::set('backups', new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter
    {
        public function writeStream($path, $resource, array $options = [])
        {
            throw UnableToWriteFile::atLocation($path, 'connection refused');
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
