<?php

namespace Tests\Feature\Commands;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * INFRA-11 / INFRA-20 — spec AC P2.6 and the cutover-rollback edge case.
 *
 * The source is a SQLite connection defined for the duration of the test only.
 * That is faithful to the scenario rather than a violation of AD-008: the
 * pre-cutover engine IS a retired one, and the command's whole job is to read
 * from it. The application's own config is never touched.
 */
class CutoverDrawsTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE = 'cutover_source';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.'.self::SOURCE => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        Schema::connection(self::SOURCE)->create('draws', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->integer('draw_number');
            $table->date('draw_date');
            $table->json('raw_data');
            $table->timestamps();
        });
    }

    public function test_it_copies_every_draw_from_the_source_connection_into_postgres(): void
    {
        $this->seedSource(1);
        $this->seedSource(500);
        $this->seedSource(2608);

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE])
            ->assertExitCode(0);

        $this->assertSame(3, Draw::count());
        $this->assertSame(
            [1, 500, 2608],
            Draw::orderBy('draw_number')->pluck('draw_number')->all(),
        );
        $this->assertSame(
            $this->payload(500),
            Draw::where('draw_number', 500)->firstOrFail()->raw_data,
        );
    }

    public function test_a_nul_bearing_payload_is_stripped_on_copy_and_still_passes_validation(): void
    {
        $payload = $this->payload(2194);
        $this->assertStringContainsString("\0", $payload['nomeTimeCoracaoMesSorte']);

        $this->seedSource(2194);

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE])
            ->assertExitCode(0);

        $copied = Draw::where('draw_number', 2194)->firstOrFail();
        $this->assertSame('', $copied->raw_data['nomeTimeCoracaoMesSorte']);
        $this->assertSame($payload['listaDezenas'], $copied->raw_data['listaDezenas']);
    }

    public function test_dry_run_reports_the_row_count_without_writing_anything(): void
    {
        $this->seedSource(1);
        $this->seedSource(500);

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE, '--dry-run' => true])
            ->expectsOutputToContain('2 draws would move')
            ->assertExitCode(0);

        $this->assertSame(0, Draw::count());
    }

    public function test_validation_fails_when_row_counts_do_not_match(): void
    {
        $this->seedSource(1);
        $this->seedSource(500);
        $this->copyToTarget(1);

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE, '--validate-only' => true])
            ->expectsOutputToContain('Row-count mismatch')
            ->assertExitCode(1);
    }

    public function test_validation_fails_when_a_sampled_payload_value_was_corrupted(): void
    {
        $this->seedSource(500);
        $this->copyToTarget(500, function (array $payload): array {
            $payload['listaDezenas'][0] = '99';

            return $payload;
        });

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE, '--validate-only' => true])
            ->expectsOutputToContain('raw_data mismatch on draw 500')
            ->assertExitCode(1);
    }

    /**
     * The corruption row counts cannot see. A dropped key leaves parity intact,
     * which is precisely why the sampled deep comparison exists.
     */
    public function test_validation_fails_when_a_sampled_payload_dropped_a_key(): void
    {
        $this->seedSource(500);
        $this->copyToTarget(500, function (array $payload): array {
            unset($payload['nomeMunicipioUFSorteio']);

            return $payload;
        });

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE, '--validate-only' => true])
            ->expectsOutputToContain('raw_data mismatch on draw 500')
            ->assertExitCode(1);
    }

    public function test_a_failed_validation_leaves_the_source_snapshot_untouched(): void
    {
        $this->seedSource(500);
        $before = DB::connection(self::SOURCE)->table('draws')->get()->toArray();

        $this->copyToTarget(500, function (array $payload): array {
            $payload['acumulado'] = ! $payload['acumulado'];

            return $payload;
        });

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE, '--validate-only' => true])
            ->assertExitCode(1);

        $this->assertEquals(
            $before,
            DB::connection(self::SOURCE)->table('draws')->get()->toArray(),
        );
    }

    public function test_a_failed_validation_does_not_switch_the_default_connection(): void
    {
        $this->seedSource(500);
        $this->copyToTarget(500, function (array $payload): array {
            $payload['listaDezenas'] = [];

            return $payload;
        });

        $this->artisan('app:cutover-draws', ['--from' => self::SOURCE, '--validate-only' => true])
            ->assertExitCode(1);

        $this->assertSame('pgsql', config('database.default'));
        $this->assertSame('pgsql', DB::connection()->getDriverName());
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

    /**
     * Insert straight into the source table so NUL bytes survive — the cast
     * would strip them, and the point is that the command does the stripping.
     */
    private function seedSource(int $concurso): void
    {
        DB::connection(self::SOURCE)->table('draws')->insert([
            'type' => GamesEnum::MEGA_SENA->value,
            'draw_number' => $concurso,
            'draw_date' => '2022-01-01',
            'raw_data' => json_encode($this->payload($concurso)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Place a row in the target directly, optionally mutating the payload, to
     * manufacture the post-copy states validation must reject.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $corrupt
     */
    private function copyToTarget(int $concurso, ?callable $corrupt = null): void
    {
        $payload = $this->payload($concurso);

        Draw::create([
            'type' => GamesEnum::MEGA_SENA,
            'draw_number' => $concurso,
            'draw_date' => '2022-01-01',
            'raw_data' => $corrupt ? $corrupt($payload) : $payload,
        ]);
    }
}
