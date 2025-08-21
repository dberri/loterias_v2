<?php

namespace App\Console\Commands;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\Scraper;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ScrapeDraw extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-draws {game} {quantity} {latest_draw_number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes the quantity of draws from the lottery API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $game = GamesEnum::tryFrom($this->argument('game'));
        if (!$game) {
            $this->error('Invalid game');
            return Command::FAILURE;
        }

        $this->info('Scraping ' . $this->argument('quantity') . ' draws for ' . $game->value);

        $availableDraws = Draw::where('type', $game)
            ->orderByDesc('draw_number')
            ->get();

        $runningQuantity = $this->argument('quantity');
        for ($i = $this->argument('latest_draw_number'); $i > 0; $i--) {
            if ($availableDraws->contains('draw_number', $i)) {
                continue;
            }

            $this->info('Scraping draw ' . $game->value . ' ' . $i);
            $scraper = new Scraper($game, $i);
            $scraper->scrape();
            $runningQuantity--;

            if ($runningQuantity <= 0) {
                break;
            }
        }

        return Command::SUCCESS;
    }
}
