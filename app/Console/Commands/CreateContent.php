<?php

namespace App\Console\Commands;

use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\ContentCreator;
use Illuminate\Console\Command;

class CreateContent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-content {game} {draw_number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates content for the given game and draw number';

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

        $this->info('Creating content for ' . $game->value . ' draw ' . $this->argument('draw_number'));

        $draw = Draw::where('type', $game->value)
            ->where('draw_number', $this->argument('draw_number'))
            ->first();

        $service = new ContentCreator();
        $service->createContent($draw);

        return Command::SUCCESS;
    }
}
