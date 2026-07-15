<?php

namespace App\Console\Commands;

use App\DTOs\GenerationRequest;
use App\Enums\GamesEnum;
use App\Models\Draw;
use App\Services\Content\DrawPagePrompt;
use App\Services\PageAssembler;
use App\Services\ContentProviderManager;
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
        if (! $game) {
            $this->error('Invalid game');

            return Command::FAILURE;
        }

        $this->info('Creating content for '.$game->value.' draw '.$this->argument('draw_number'));

        $draw = Draw::where('type', $game->value)
            ->where('draw_number', (int) $this->argument('draw_number'))
            ->first();

        if (! $draw) {
            $this->error('Draw not found');

            return Command::FAILURE;
        }

        $request = new GenerationRequest(
            game: $draw->type,
            drawNumber: $draw->draw_number,
            context: DrawPagePrompt::context($draw),
            prompt: DrawPagePrompt::prompt($draw),
            schema: DrawPagePrompt::schema(),
        );

        $driverName = config('content.default', 'openai');
        $provider = app(ContentProviderManager::class)->driver($driverName);
        $result = $provider->generateOne($request);

        $page = (new PageAssembler)->assemble($draw, $result);
        $page->update(['provider' => $driverName]);

        return Command::SUCCESS;
    }
}
