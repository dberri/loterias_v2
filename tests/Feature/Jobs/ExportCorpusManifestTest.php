<?php

namespace Tests\Feature\Jobs;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use App\Models\Page;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * INFRA-12 / INFRA-13 — spec AC P3.1 and P3.2.
 *
 * The manifest is what turns a pile of files into a backup: it says when the
 * export ran, how much it should contain, and what each artifact must hash to.
 * A manifest that was never checked against the bytes on disk would only
 * document the export's intentions.
 */
class ExportCorpusManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    public function test_it_writes_a_manifest_alongside_the_artifacts(): void
    {
        $this->seedDraw(500);

        (new ExportCorpus)->handle();

        Storage::disk('backups')->assertExists($this->path('manifest.json'));
        $this->assertIsArray($this->manifest());
    }

    public function test_the_manifest_records_the_export_timestamp(): void
    {
        $this->travelTo('2026-07-18 03:15:00');
        $this->seedDraw(500);

        (new ExportCorpus)->handle();

        $this->assertSame(
            now()->toIso8601String(),
            $this->manifest()['exported_at'],
        );
    }

    public function test_the_manifest_records_a_truthful_row_count_for_every_table(): void
    {
        $this->seedDraw(1);
        $this->seedDraw(500);
        Page::factory()->count(4)->create();

        (new ExportCorpus)->handle();

        $manifest = $this->manifest();

        $this->assertSame(2, $manifest['tables']['draws']['rows']);
        $this->assertSame(4, $manifest['tables']['pages']['rows']);
    }

    /**
     * INFRA-19's truthful zero, carried into the manifest: an empty table is
     * reported as 0 rows, not omitted.
     */
    public function test_an_empty_table_is_recorded_with_a_row_count_of_zero(): void
    {
        $this->seedDraw(500);
        $this->assertSame(0, Page::count());

        (new ExportCorpus)->handle();

        $this->assertSame(0, $this->manifest()['tables']['pages']['rows']);
    }

    public function test_the_manifest_records_a_sha256_matching_each_artifact_on_disk(): void
    {
        $this->seedDraw(500);
        Page::factory()->create();

        (new ExportCorpus)->handle();

        foreach ($this->manifest()['tables'] as $meta) {
            $this->assertSame(
                hash('sha256', Storage::disk('backups')->get($this->path($meta['artifact']))),
                $meta['sha256'],
            );
        }
    }

    public function test_the_manifest_records_the_app_and_schema_version(): void
    {
        $this->seedDraw(500);

        (new ExportCorpus)->handle();

        $manifest = $this->manifest();

        $ran = app('migrator')->getRepository()->getRan();

        $this->assertSame((string) config('app.version', app()->version()), $manifest['app_version']);
        $this->assertNotEmpty($manifest['app_version']);
        $this->assertSame((string) end($ran), $manifest['schema_version']);
    }

    public function test_the_manifest_omits_a_table_that_does_not_exist(): void
    {
        Schema::drop((new Page)->getTable());
        $this->seedDraw(500);

        (new ExportCorpus)->handle();

        $this->assertArrayHasKey('draws', $this->manifest()['tables']);
        $this->assertArrayNotHasKey('pages', $this->manifest()['tables']);
    }

    /**
     * The feature's single most dangerous failure, manufactured.
     *
     * The disk is made to hand back different bytes than were written — the
     * exact behaviour of storage that accepted a write and stored something
     * else. The export must notice during its own run and fail, rather than
     * finish and leave a manifest asserting the backup is sound.
     */
    public function test_the_export_fails_when_the_disk_returns_different_bytes_than_were_written(): void
    {
        $this->seedDraw(500);
        $this->makeBackupDiskCorruptOnRead();

        try {
            (new ExportCorpus)->handle();
            $this->fail('The export completed despite an artifact that does not match what was written.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('failed checksum verification', $e->getMessage());
        }

        Storage::disk('backups')->assertMissing($this->path('manifest.json'));
    }

    /**
     * An artifact corrupted after the export is detected when its checksum is
     * re-checked against the manifest — the same guard a restore relies on.
     */
    public function test_an_artifact_corrupted_after_the_export_fails_verification(): void
    {
        $this->seedDraw(500);

        $job = new ExportCorpus;
        $job->handle();

        $manifest = $this->manifest();
        Storage::disk('backups')->put(
            $this->path('draws.ndjson'),
            Storage::disk('backups')->get($this->path('draws.ndjson')).'{"id":999}'."\n",
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed checksum verification/');

        $job->verifyArtifacts($this->directory(), $manifest);
    }

    private function directory(): string
    {
        return 'exports/'.now()->format('Y-m-d');
    }

    private function path(string $artifact): string
    {
        return $this->directory().'/'.$artifact;
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        return json_decode(
            Storage::disk('backups')->get($this->path('manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * Swap the backups disk for one that silently alters every artifact it
     * hands back on read.
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
