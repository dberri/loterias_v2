<?php

namespace Tests\Feature\Commands\CutoverDrawsTest;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * INFRA-11 / INFRA-20 — spec AC P2.6 and the cutover-rollback edge case.
 *
 * The source is a SQLite connection defined for the duration of the test only.
 * That is faithful to the scenario rather than a violation of AD-008: the
 * pre-cutover engine IS a retired one, and the command's whole job is to read
 * from it. The application's own config is never touched.
 */
const SOURCE = 'cutover_source';

beforeEach(function () {
    config(['database.connections.'.SOURCE => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]]);

    Schema::connection(SOURCE)->create('draws', function (Blueprint $table) {
        $table->id();
        $table->string('type');
        $table->integer('draw_number');
        $table->date('draw_date');
        $table->json('raw_data');
        $table->timestamps();
    });
});

test('it copies every draw from the source connection into postgres', function () {
    seedSource(1);
    seedSource(500);
    seedSource(2608);

    $this->artisan('app:cutover-draws', ['--from' => SOURCE])
        ->assertExitCode(0);

    expect(Draw::count())->toBe(3);
    expect(Draw::orderBy('draw_number')->pluck('draw_number')->all())->toBe([1, 500, 2608]);
    expect(Draw::where('draw_number', 500)->firstOrFail()->raw_data)->toBe(payload(500));
});

test('a nul bearing payload is stripped on copy and still passes validation', function () {
    $payload = payload(2194);
    $this->assertStringContainsString("\0", $payload['nomeTimeCoracaoMesSorte']);

    seedSource(2194);

    $this->artisan('app:cutover-draws', ['--from' => SOURCE])
        ->assertExitCode(0);

    $copied = Draw::where('draw_number', 2194)->firstOrFail();
    expect($copied->raw_data['nomeTimeCoracaoMesSorte'])->toBe('');
    expect($copied->raw_data['listaDezenas'])->toBe($payload['listaDezenas']);
});

test('dry run reports the row count without writing anything', function () {
    seedSource(1);
    seedSource(500);

    $this->artisan('app:cutover-draws', ['--from' => SOURCE, '--dry-run' => true])
        ->expectsOutputToContain('2 draws would move')
        ->assertExitCode(0);

    expect(Draw::count())->toBe(0);
});

test('validation fails when row counts do not match', function () {
    seedSource(1);
    seedSource(500);
    copyToTarget(1);

    $this->artisan('app:cutover-draws', ['--from' => SOURCE, '--validate-only' => true])
        ->expectsOutputToContain('Row-count mismatch')
        ->assertExitCode(1);
});

test('validation fails when a sampled payload value was corrupted', function () {
    seedSource(500);
    copyToTarget(500, function (array $payload): array {
        $payload['listaDezenas'][0] = '99';

        return $payload;
    });

    $this->artisan('app:cutover-draws', ['--from' => SOURCE, '--validate-only' => true])
        ->expectsOutputToContain('raw_data mismatch on draw 500')
        ->assertExitCode(1);
});

/**
 * The corruption row counts cannot see. A dropped key leaves parity intact,
 * which is precisely why the sampled deep comparison exists.
 */
test('validation fails when a sampled payload dropped a key', function () {
    seedSource(500);
    copyToTarget(500, function (array $payload): array {
        unset($payload['nomeMunicipioUFSorteio']);

        return $payload;
    });

    $this->artisan('app:cutover-draws', ['--from' => SOURCE, '--validate-only' => true])
        ->expectsOutputToContain('raw_data mismatch on draw 500')
        ->assertExitCode(1);
});

test('a failed validation leaves the source snapshot untouched', function () {
    seedSource(500);
    $before = DB::connection(SOURCE)->table('draws')->get()->toArray();

    copyToTarget(500, function (array $payload): array {
        $payload['acumulado'] = ! $payload['acumulado'];

        return $payload;
    });

    $this->artisan('app:cutover-draws', ['--from' => SOURCE, '--validate-only' => true])
        ->assertExitCode(1);

    expect(DB::connection(SOURCE)->table('draws')->get()->toArray())->toEqual($before);
});

test('a failed validation does not switch the default connection', function () {
    seedSource(500);
    copyToTarget(500, function (array $payload): array {
        $payload['listaDezenas'] = [];

        return $payload;
    });

    $this->artisan('app:cutover-draws', ['--from' => SOURCE, '--validate-only' => true])
        ->assertExitCode(1);

    expect(config('database.default'))->toBe('pgsql');
    expect(DB::connection()->getDriverName())->toBe('pgsql');
});

/**
 * @return array<string, mixed>
 */
function payload(int $concurso): array
{
    $path = database_path("seeders/lotteries/megasena/draws/{$concurso}.json");
    expect($path)->toBeFile();

    return json_decode(file_get_contents($path), true);
}

/**
 * Insert straight into the source table so NUL bytes survive — the cast
 * would strip them, and the point is that the command does the stripping.
 */
function seedSource(int $concurso): void
{
    DB::connection(SOURCE)->table('draws')->insert([
        'type' => GamesEnum::MEGA_SENA->value,
        'draw_number' => $concurso,
        'draw_date' => '2022-01-01',
        'raw_data' => json_encode(payload($concurso)),
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
function copyToTarget(int $concurso, ?callable $corrupt = null): void
{
    $payload = payload($concurso);

    Draw::create([
        'type' => GamesEnum::MEGA_SENA,
        'draw_number' => $concurso,
        'draw_date' => '2022-01-01',
        'raw_data' => $corrupt ? $corrupt($payload) : $payload,
    ]);
}
