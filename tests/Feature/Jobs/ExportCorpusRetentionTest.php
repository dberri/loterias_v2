<?php

namespace Tests\Feature\Jobs;

use App\Enums\GamesEnum;
use App\Jobs\ExportCorpus;
use App\Models\Draw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * INFRA-17 — spec AC P3.6: "daily artifacts SHALL be kept for 35 days and
 * monthly artifacts for 12 months."
 *
 * The spec originally required this to be "enforced by object-storage lifecycle
 * rules, not application code". That is unsatisfiable for the monthly half: a
 * lifecycle rule matches on prefix, tag and age, and has no way to express
 * "keep the first export of each month". Selecting the monthly artifact is
 * necessarily writer-side, so the writer is what these tests cover (AD-013).
 *
 * The daily half genuinely is a lifecycle rule and is not testable here — it is
 * bucket configuration, documented in docs/infrastructure/backup-retention.md.
 */
class ExportCorpusRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('backups');
    }

    public function test_an_export_is_promoted_into_the_monthly_tier(): void
    {
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        Storage::disk('backups')->assertExists($this->monthly('draws.ndjson'));
        Storage::disk('backups')->assertExists($this->monthly('manifest.json'));
    }

    /**
     * The whole point of a 12-month tier is that it outlives the 35-day one, so
     * it must not sit under the prefix the 35-day expiry rule matches. Nesting
     * monthly/ inside exports/ would let the daily rule silently delete the
     * long-term backups — a backup system that eats its own backups.
     */
    public function test_the_monthly_tier_is_a_sibling_of_the_daily_prefix_not_nested_inside_it(): void
    {
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        $this->assertStringStartsNotWith('exports/', $this->monthly('manifest.json'));

        foreach (Storage::disk('backups')->allFiles('monthly') as $path) {
            $this->assertStringStartsNotWith('exports/', $path);
        }
    }

    /**
     * One artifact per month, not per night. A later run in the same month must
     * leave the existing monthly artifact alone, otherwise the "monthly" tier is
     * really just a second copy of the most recent daily.
     */
    public function test_a_later_export_in_the_same_month_does_not_replace_the_monthly_artifact(): void
    {
        $this->travelTo('2026-03-04 03:30:00');
        $this->seedDraw(500);
        ExportCorpus::dispatchSync();

        $first = Storage::disk('backups')->get($this->monthly('draws.ndjson'));

        $this->travelTo('2026-03-19 03:30:00');
        $this->seedDraw(2194);
        ExportCorpus::dispatchSync();

        $this->assertSame($first, Storage::disk('backups')->get($this->monthly('draws.ndjson')));
        $this->assertStringNotContainsString('"draw_number":2194', $first);
    }

    public function test_each_month_gets_its_own_artifact(): void
    {
        $this->travelTo('2026-03-19 03:30:00');
        $this->seedDraw(500);
        ExportCorpus::dispatchSync();

        $this->travelTo('2026-04-02 03:30:00');
        ExportCorpus::dispatchSync();

        Storage::disk('backups')->assertExists('monthly/2026-03/manifest.json');
        Storage::disk('backups')->assertExists('monthly/2026-04/manifest.json');
    }

    /**
     * Promotion keys on absence rather than on the calendar date, so a month
     * whose first night failed is still covered by the next successful run. The
     * next run is the retry (AD-007) — a "promote only on the 1st" rule would
     * lose a whole month to one bad night.
     */
    public function test_a_month_whose_first_nights_were_missed_is_still_promoted_later(): void
    {
        $this->travelTo('2026-03-17 03:30:00');
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        Storage::disk('backups')->assertExists('monthly/2026-03/manifest.json');
    }

    /**
     * The monthly copy must be verifiable by the same procedure as a daily one,
     * or it is a backup nobody can check.
     */
    public function test_the_monthly_artifact_matches_the_checksum_its_manifest_records(): void
    {
        $this->seedDraw(500);
        $this->seedDraw(2194);

        ExportCorpus::dispatchSync();

        $manifest = json_decode(
            Storage::disk('backups')->get($this->monthly('manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        foreach ($manifest['tables'] as $meta) {
            $this->assertSame(
                $meta['sha256'],
                hash('sha256', Storage::disk('backups')->get($this->monthly($meta['artifact']))),
            );
        }
    }

    public function test_the_monthly_artifact_holds_the_same_bytes_as_the_daily_it_was_promoted_from(): void
    {
        $this->seedDraw(500);

        ExportCorpus::dispatchSync();

        $this->assertSame(
            Storage::disk('backups')->get('exports/'.now()->format('Y-m-d').'/draws.ndjson'),
            Storage::disk('backups')->get($this->monthly('draws.ndjson')),
        );
    }

    private function monthly(string $artifact): string
    {
        return 'monthly/'.now()->format('Y-m').'/'.$artifact;
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
