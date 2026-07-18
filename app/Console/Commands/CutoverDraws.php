<?php

namespace App\Console\Commands;

use App\Models\Draw;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Backfills `draws` from the pre-cutover engine into PostgreSQL and validates
 * the result before anything is switched over (INFRA-11, INFRA-20).
 *
 * The command is strictly read-only against the source: it never writes, drops,
 * or mutates the pre-cutover snapshot, so a failed validation is always
 * recoverable by simply not switching the connection config.
 */
class CutoverDraws extends Command
{
    protected $signature = 'app:cutover-draws
        {--from= : Connection holding the pre-cutover draws}
        {--to= : Connection to write into (defaults to the application default)}
        {--sample=25 : How many payloads to deep-compare during validation}
        {--dry-run : Report what would move without writing anything}
        {--validate-only : Validate an already-populated target without copying}';

    protected $description = 'Backfill draws into PostgreSQL and validate row-count parity plus sampled raw_data equality';

    public function handle(): int
    {
        $source = (string) $this->option('from');

        if ($source === '') {
            $this->error('A source connection is required: --from=<connection>.');

            return self::FAILURE;
        }

        $target = (string) ($this->option('to') ?: config('database.default'));
        $sourceCount = Draw::on($source)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$sourceCount} draws would move from [{$source}] to [{$target}]. Nothing was written.");

            return self::SUCCESS;
        }

        if (! $this->option('validate-only')) {
            $this->copy($source, $target);
        }

        return $this->validateCutover($source, $target, $sourceCount);
    }

    /**
     * Stream the source table into the target, preserving identifiers and
     * timestamps. Writing through the Draw model is what applies the NUL-safe
     * cast (AD-012) to every backfilled payload.
     */
    private function copy(string $source, string $target): void
    {
        Draw::on($source)->orderBy('id')->chunk(500, function (Collection $draws) use ($target): void {
            foreach ($draws as $draw) {
                $copy = (new Draw)->setConnection($target);
                $copy->timestamps = false;
                $copy->forceFill([
                    'id' => $draw->id,
                    'type' => $draw->type,
                    'draw_number' => $draw->draw_number,
                    'draw_date' => $draw->draw_date,
                    'raw_data' => $draw->raw_data,
                    'created_at' => $draw->created_at,
                    'updated_at' => $draw->updated_at,
                ]);
                $copy->save();
            }
        });
    }

    /**
     * Row-count parity alone cannot detect a corrupted payload — the rows still
     * count — so parity is paired with a deep comparison of a random sample.
     */
    private function validateCutover(string $source, string $target, int $sourceCount): int
    {
        $targetCount = Draw::on($target)->count();

        if ($targetCount !== $sourceCount) {
            $this->error("Row-count mismatch: source [{$source}] has {$sourceCount} draws, target [{$target}] has {$targetCount}.");

            return self::FAILURE;
        }

        $sample = Draw::on($source)
            ->inRandomOrder()
            ->limit(max(1, (int) $this->option('sample')))
            ->get();

        foreach ($sample as $draw) {
            $copy = Draw::on($target)
                ->where('type', $draw->type->value)
                ->where('draw_number', $draw->draw_number)
                ->first();

            if ($copy === null) {
                $this->error("Draw {$draw->draw_number} is missing from target [{$target}].");

                return self::FAILURE;
            }

            if (json_encode($copy->raw_data) !== $this->tolerated($draw->raw_data)) {
                $this->error("raw_data mismatch on draw {$draw->draw_number}: the target payload differs from the source beyond NUL-byte stripping.");

                return self::FAILURE;
            }
        }

        $this->info("Cutover validated: {$sourceCount} draws present in [{$target}], {$sample->count()} payloads deep-compared.");

        return self::SUCCESS;
    }

    /**
     * The only difference tolerated between a source and target payload is the
     * NUL stripping AD-012 performs at the storage boundary.
     *
     * Both sides are re-encoded and only the JSON NUL escape is removed from
     * the source encoding, so a dropped key, a reordered list, or a changed
     * value all remain mismatches. This deliberately does not reuse
     * NulSafeJson: a bug in the cast must surface here rather than cancel
     * itself out.
     *
     * @param  array<mixed>|null  $rawData
     */
    private function tolerated(?array $rawData): string
    {
        $nulEscape = trim((string) json_encode(chr(0)), '"');

        return str_replace($nulEscape, '', (string) json_encode($rawData));
    }
}
