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

        $this->writeNdjson(
            $directory.'/draws.ndjson',
            Draw::query()->orderBy('id')->lazy(self::CHUNK),
        );

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
            $this->writeNdjson(
                $directory.'/pages.ndjson',
                Page::query()->orderBy('id')->lazy(self::CHUNK),
            );
        }
    }

    private function pagesTableExists(): bool
    {
        return Schema::hasTable((new Page)->getTable());
    }

    /**
     * Stream rows into an NDJSON artifact, one record per line.
     *
     * @param  LazyCollection<int, \Illuminate\Database\Eloquent\Model>  $rows
     * @return int the number of records written
     */
    private function writeNdjson(string $path, LazyCollection $rows): int
    {
        $stream = fopen('php://temp/maxmemory:'.(4 * 1024 * 1024), 'r+');
        $count = 0;

        foreach ($rows as $row) {
            fwrite($stream, $this->encode($row->getAttributes())."\n");
            $count++;
        }

        rewind($stream);
        $this->disk()->writeStream($path, $stream);
        fclose($stream);

        return $count;
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
