<?php

namespace Tests\Feature\Jobs;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Mail\OperatorAlert;
use App\Models\Draw;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToWriteFile;
use Tests\TestCase;
use Throwable;

/**
 * INFRA-13 / INFRA-14 — spec AC P3.2 and P3.3.
 *
 * "A silently failing backup is worse than no backup, because it manufactures
 * false confidence." Every assertion here is about the failure being audible.
 */
class ExportCorpusAlertingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
        Mail::fake();
    }

    public function test_a_checksum_mismatch_alerts_the_operator(): void
    {
        $this->seedDraw(500);
        $this->makeBackupDiskCorruptOnRead();

        $this->runFailingExport();

        Mail::assertSent(OperatorAlert::class);
    }

    public function test_unreachable_storage_alerts_rather_than_exiting_zero(): void
    {
        $this->seedDraw(500);
        $this->makeBackupDiskUnwritable();

        $threw = false;
        try {
            ExportCorpus::dispatchSync();
        } catch (Throwable) {
            $threw = true;
        }

        $this->assertTrue($threw, 'The export swallowed an unreachable-storage failure and exited zero.');
        Mail::assertSent(OperatorAlert::class);
    }

    public function test_the_failure_is_rethrown_so_the_job_does_not_report_success(): void
    {
        $this->seedDraw(500);
        $this->makeBackupDiskCorruptOnRead();

        $this->expectException(Throwable::class);

        ExportCorpus::dispatchSync();
    }

    /**
     * AD-009: Laravel's own failed_jobs table is the dead-letter queue. A
     * failure that alerts but never lands there leaves no record to inspect
     * after the email is read and forgotten.
     */
    public function test_a_failed_export_lands_in_the_failed_jobs_table(): void
    {
        $this->seedDraw(500);
        $this->makeBackupDiskCorruptOnRead();

        config(['queue.default' => 'database']);
        Queue::setDefaultDriver('database');

        ExportCorpus::dispatch();

        $this->artisan('queue:work', ['--once' => true, '--tries' => 1])->run();

        $this->assertSame(1, (int) DB::table('failed_jobs')->count());
    }

    /**
     * The reason AlertNotifier exists (AD-011), exercised through its real
     * caller: a backup broken every night for a week must email once.
     */
    public function test_repeated_nightly_failures_send_only_one_email(): void
    {
        $this->seedDraw(500);
        $this->makeBackupDiskCorruptOnRead();

        $this->runFailingExport();
        $this->travel(1)->days();
        $this->runFailingExport();
        $this->travel(1)->days();
        $this->runFailingExport();

        Mail::assertSentCount(1);
    }

    public function test_a_successful_export_sends_no_alert(): void
    {
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        Mail::assertNothingSent();
    }

    private function runFailingExport(): void
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
    private function makeBackupDiskCorruptOnRead(): void
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
    private function makeBackupDiskUnwritable(): void
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
