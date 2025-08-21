<?php

namespace App\Console\Commands;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\ContentCreator;
use Illuminate\Console\Command;

class CreatePages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-pages {game} {quantity}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates pages for the given game and quantity';

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

        $this->info('Creating ' . $this->argument('quantity') . ' pages for ' . $game->value . ' draws');

        $draws = Draw::where('type', $game)
            ->withoutPage()
            ->orderByDesc('draw_number')
            ->limit($this->argument('quantity'))
            ->get();

        $service = new ContentCreator();
        $service->createContentForDraws($draws);
    }
}
