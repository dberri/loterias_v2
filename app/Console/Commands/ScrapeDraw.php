<?php

namespace App\Console\Commands;

use App\Enums\GamesEnum;
use App\Services\Scraper;
use Illuminate\Console\Command;

class ScrapeDraw extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:scrape-draw {game} {draw_number?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrapes the latest draw from the lottery API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $game = GamesEnum::tryFrom($this->argument('game'));
        if (! $game) {
            $this->error('Invalid game');

            return Command::FAILURE;
        }

        $scraper = new Scraper($game, $this->argument('draw_number'));
        $scraper->scrape();

        return Command::SUCCESS;
    }
}
