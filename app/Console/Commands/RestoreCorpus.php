<?php

namespace App\Console\Commands;

use App\Models\Draw;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reconstructs the corpus from an export artifact directory (INFRA-15).
 *
 * The artifacts are NDJSON precisely so this command is not the only way back —
 * see docs/infrastructure/restore-runbook.md for the jq-and-shell fallback. This
 * command exists because INFRA-16 requires a *timed* restore, and timing a
 * hand-run sequence of shell steps measures the operator, not the procedure.
 */
class RestoreCorpus extends Command
{
    protected $signature = 'app:restore-corpus
        {directory : Export directory on the backup disk, e.g. exports/2026-07-18}
        {--disk= : Disk holding the artifacts (defaults to the configured backup disk)}';

    protected $description = 'Restore draws and pages from an export artifact into an empty database';

    /**
     * Import order matters: pages carry a foreign key to draws.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'draws' => Draw::class,
        'pages' => Page::class,
    ];

    public function handle(): int
    {
        $directory = rtrim($this->argument('directory'), '/');

        $manifest = $this->readManifest($directory);

        if ($manifest === null) {
            return self::FAILURE;
        }

        $tables = $this->orderedTables($manifest);

        /*
         * The checksum gate runs over every artifact BEFORE a single row is
         * imported. Restoring from a corrupt artifact is worse than refusing to
         * restore at all: a refusal sends you to the previous night's backup,
         * while a half-imported corrupt one looks like a successful recovery.
         */
        foreach ($tables as $table => $meta) {
            $path = $directory.'/'.$meta['artifact'];
            $actual = $this->checksum($path);

            if ($actual === null) {
                $this->error("Artifact [{$path}] named in the manifest is missing or unreadable. Nothing was restored.");

                return self::FAILURE;
            }

            if ($actual !== $meta['sha256']) {
                $this->error("Artifact [{$path}] failed checksum verification: manifest records {$meta['sha256']}, the artifact hashes to {$actual}. Nothing was restored.");

                return self::FAILURE;
            }
        }

        foreach ($tables as $table => $meta) {
            $model = self::MODELS[$table];

            if ($model::query()->count() > 0) {
                $this->error("Table [{$table}] is not empty. A restore targets an empty database; refusing to merge into existing data.");

                return self::FAILURE;
            }
        }

        foreach ($tables as $table => $meta) {
            $imported = $this->import(self::MODELS[$table], $directory.'/'.$meta['artifact']);
            $this->info("Restored {$imported} rows into [{$table}].");
        }

        foreach ($tables as $table => $meta) {
            $actual = self::MODELS[$table]::query()->count();

            if ($actual !== $meta['rows']) {
                $this->error("Row-count mismatch after restoring [{$table}]: the manifest records {$meta['rows']} rows, the database holds {$actual}.");

                return self::FAILURE;
            }
        }

        $this->resetSequences(array_keys($tables));

        $this->info('Restore complete and verified against the manifest.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readManifest(string $directory): ?array
    {
        $path = $directory.'/manifest.json';

        if (! $this->disk()->exists($path)) {
            $this->error("No manifest found at [{$path}]. An artifact directory without a manifest cannot be verified, so it is not restorable.");

            return null;
        }

        return json_decode((string) $this->disk()->get($path), true);
    }

    /**
     * Only tables this application knows how to rebuild, in dependency order.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, array{artifact: string, rows: int, sha256: string}>
     */
    private function orderedTables(array $manifest): array
    {
        $tables = [];

        foreach (array_keys(self::MODELS) as $table) {
            if (isset($manifest['tables'][$table])) {
                $tables[$table] = $manifest['tables'][$table];
            }
        }

        return $tables;
    }

    private function checksum(string $path): ?string
    {
        $stream = $this->disk()->readStream($path);

        if (! is_resource($stream)) {
            return null;
        }

        $hash = hash_init('sha256');
        hash_update_stream($hash, $stream);
        fclose($stream);

        return hash_final($hash);
    }

    /**
     * Read the artifact a line at a time so restoring a large corpus does not
     * require holding it in memory.
     *
     * @param  class-string<Model>  $model
     */
    private function import(string $model, string $path): int
    {
        $stream = $this->disk()->readStream($path);
        $count = 0;

        while (($line = fgets($stream)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $row = new $model;
            $row->timestamps = false;
            $row->forceFill(json_decode($line, true, flags: JSON_THROW_ON_ERROR));
            $row->save();

            $count++;
        }

        fclose($stream);

        return $count;
    }

    /**
     * Rows are restored with their original identifiers, which leaves each
     * table's identity sequence still sitting at 1 — so the first row written
     * after a restore would collide on the primary key.
     *
     * A database that cannot accept a new row is not "fully reconstructed"
     * (spec AC P3.4), so the sequences are advanced past the restored maximum.
     * This is intentionally PostgreSQL-specific (INFRA-10): Postgres is the
     * canonical engine everywhere per AD-008, and identity sequences have no
     * portable equivalent.
     *
     * @param  list<string>  $tables
     */
    private function resetSequences(array $tables): void
    {
        foreach ($tables as $table) {
            DB::statement(
                "SELECT setval(pg_get_serial_sequence('{$table}', 'id'),"
                ." COALESCE((SELECT MAX(id) FROM {$table}), 1),"
                ." (SELECT MAX(id) FROM {$table}) IS NOT NULL)"
            );
        }
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->option('disk') ?: config('filesystems.backup_disk'));
    }
}
