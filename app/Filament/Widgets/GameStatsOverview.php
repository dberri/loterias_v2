<?php

namespace App\Filament\Widgets;

use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GameStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return array_map(function (GamesEnum $game): Stat {
            $drawCount = Draw::query()->where('type', $game)->count();

            $pageQuery = Page::query()->whereHas('draw', fn ($query) => $query->where('type', $game));
            $totalPages = $pageQuery->count();
            $publishedPages = (clone $pageQuery)->where('status', PageStatus::Published)->count();

            return Stat::make((new Draw(['type' => $game]))->game_name, "{$drawCount} sorteios")
                ->description("{$totalPages} páginas · {$publishedPages} publicadas");
        }, GamesEnum::cases());
    }
}
