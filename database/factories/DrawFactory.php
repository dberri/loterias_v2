<?php

namespace Database\Factories;

use App\Enums\GamesEnum;
use App\Models\Draw;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Draw>
 */
class DrawFactory extends Factory
{
    protected $model = Draw::class;

    public function definition(): array
    {
        return $this->fixtureAttributes(GamesEnum::MEGA_SENA, 2608);
    }

    public function fixture(string|GamesEnum $game, int $drawNumber): static
    {
        return $this->state(fn () => $this->fixtureAttributes($game, $drawNumber));
    }

    public function accumulated(): static
    {
        return $this->fixture(GamesEnum::LOTOFACIL, 10);
    }

    public function withWinners(): static
    {
        return $this->fixture(GamesEnum::LOTOFACIL, 1);
    }

    public function withWinnerCities(): static
    {
        return $this->fixture(GamesEnum::LOTOFACIL, 1);
    }

    public function oldDezenaFormat(): static
    {
        return $this->fixture(GamesEnum::MEGA_SENA, 1);
    }

    public function newDezenaFormat(): static
    {
        return $this->fixture(GamesEnum::MEGA_SENA, 2608);
    }

    public function emptyNextDrawDate(): static
    {
        return $this->fixture(GamesEnum::MEGA_SENA, 1);
    }

    private function fixtureAttributes(string|GamesEnum $game, int $drawNumber): array
    {
        $gameValue = $game instanceof GamesEnum ? $game->value : $game;
        $path = database_path("seeders/lotteries/{$gameValue}/draws/{$drawNumber}.json");
        $rawData = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return [
            'type' => $gameValue,
            'draw_number' => $drawNumber,
            'draw_date' => Carbon::createFromFormat('d/m/Y', $rawData['dataApuracao'])->format('Y-m-d'),
            'raw_data' => $rawData,
        ];
    }
}
