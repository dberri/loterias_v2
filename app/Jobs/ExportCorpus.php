<?php

namespace App\Jobs;

use App\Models\Draw;
use App\Models\Page;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use RuntimeException;

/**
 * Layer 2 of the backup strategy: a portable, provider-independent export of
 * the corpus to object storage (INFRA-12).
 *
 * The artifact format is NDJSON so that a restore needs neither Postgres dump
 * tooling nor this application's code — `jq` and a shell loop are enough. A
 * backup that can only be restored with the exact stack you just lost is a bet,
 * not insurance.
 */
class ExportCorpus implements ShouldQueue
{
    use Queueable;

    /**
     * Rows read per batch. The corpus is streamed rather than materialised so
     * memory use stays flat as the table grows.
     */
    private const CHUNK = 500;

    public function handle(): void
    {
        $directory = 'exports/'.now()->format('Y-m-d');

        $tables = [
            'draws' => ['artifact' => 'draws.ndjson'] + $this->writeNdjson(
                $directory.'/draws.ndjson',
                Draw::query()->orderBy('id')->lazy(self::CHUNK),
            ),
        ];

        /*
         * `pages` is created by seo-draw-page-generation, a soft dependency.
         * When it has not shipped yet the export covers draws alone and still
         * succeeds — this feature has to be deployable on its own (INFRA-18).
         *
         * When the table exists but holds no rows the artifact is still
         * written, empty (INFRA-19). A zero-row file and a missing file mean
         * very different things during a restore, and collapsing them would
         * make an empty backup indistinguishable from a backup that never ran.
         */
        if ($this->pagesTableExists()) {
            $tables['pages'] = ['artifact' => 'pages.ndjson'] + $this->writeNdjson(
                $directory.'/pages.ndjson',
                Page::query()->orderBy('id')->lazy(self::CHUNK),
            );
        }

        $manifest = [
            'exported_at' => now()->toIso8601String(),
            'app_version' => (string) config('app.version', app()->version()),
            'schema_version' => $this->schemaVersion(),
            'tables' => $tables,
        ];

        /*
         * Verify BEFORE the manifest lands. "The write returned success" is not
         * verification — the artifact is read back off the disk and re-hashed,
         * and only then is a manifest allowed to exist claiming the export is
         * good. A corrupt export therefore never leaves behind a manifest
         * vouching for it.
         */
        $this->verifyArtifacts($directory, $manifest);

        $this->disk()->put(
            $directory.'/manifest.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * Re-read every artifact the manifest describes and confirm it still
     * hashes to the recorded sha256.
     *
     * @param  array<string, mixed>  $manifest
     *
     * @throws RuntimeException when an artifact is unreadable or has changed
     */
    public function verifyArtifacts(string $directory, array $manifest): void
    {
        foreach ($manifest['tables'] as $meta) {
            $path = $directory.'/'.$meta['artifact'];
            $actual = $this->checksumOnDisk($path);

            if ($actual !== $meta['sha256']) {
                throw new RuntimeException(
                    "Export artifact [{$path}] failed checksum verification: "
                    ."manifest recorded {$meta['sha256']}, the stored artifact hashes to {$actual}."
                );
            }
        }
    }

    /**
     * Hash an artifact as it actually exists on the disk, streamed so a large
     * corpus is never pulled into memory just to be verified.
     */
    private function checksumOnDisk(string $path): string
    {
        $stream = $this->disk()->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException("Export artifact [{$path}] could not be read back for verification.");
        }

        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        return hash_final($hash);
    }

    /**
     * The most recently applied migration, so a restore can tell which schema
     * the artifact was produced against.
     */
    private function schemaVersion(): ?string
    {
        $ran = app('migrator')->getRepository()->getRan();

        return empty($ran) ? null : (string) end($ran);
    }

    private function pagesTableExists(): bool
    {
        return Schema::hasTable((new Page)->getTable());
    }

    /**
     * Stream rows into an NDJSON artifact, one record per line.
     *
     * The sha256 is accumulated while streaming, so verification costs no
     * extra pass over the data at write time.
     *
     * @param  LazyCollection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return array{rows: int, sha256: string}
     */
    private function writeNdjson(string $path, LazyCollection $rows): array
    {
        $stream = fopen('php://temp/maxmemory:'.(4 * 1024 * 1024), 'r+');
        $hash = hash_init('sha256');
        $count = 0;

        foreach ($rows as $row) {
            $line = $this->encode($row->getAttributes())."\n";
            hash_update($hash, $line);
            fwrite($stream, $line);
            $count++;
        }

        rewind($stream);
        $this->disk()->writeStream($path, $stream);
        fclose($stream);

        return ['rows' => $count, 'sha256' => hash_final($hash)];
    }

    /**
     * Emit one record as a single line of JSON.
     *
     * JSON columns are decoded first so the artifact holds real nested objects
     * rather than escaped strings — that is what makes it navigable with `jq`.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function encode(array $attributes): string
    {
        foreach (['raw_data', 'blocks'] as $jsonColumn) {
            if (isset($attributes[$jsonColumn]) && is_string($attributes[$jsonColumn])) {
                $attributes[$jsonColumn] = json_decode($attributes[$jsonColumn], true);
            }
        }

        return (string) json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.backup_disk'));
    }
}
