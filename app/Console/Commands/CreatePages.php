<?php

namespace App\Console\Commands;

use App\Contracts\BatchContentProvider;
use App\DTOs\GenerationRequest;
use App\Enums\GamesEnum;
use App\Enums\PageStatus;
use App\Models\Draw;
use App\Models\Page;
use App\Services\Content\DrawPagePrompt;
use App\Services\ContentProviderManager;
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
        if (! $game) {
            $this->error('Invalid game');

            return Command::FAILURE;
        }

        $this->info('Creating '.$this->argument('quantity').' pages for '.$game->value.' draws');

        $draws = Draw::where('type', $game->value)
            ->withoutPage()
            ->orderByDesc('draw_number')
            ->limit((int) $this->argument('quantity'))
            ->get();

        if ($draws->isEmpty()) {
            $this->info('No eligible draws found.');

            return Command::SUCCESS;
        }

        $manager = app(ContentProviderManager::class);
        $driverName = config('content.default', 'openai');
        /** @var BatchContentProvider $provider */
        $provider = $manager->driver($driverName);

        $requests = $draws->map(function (Draw $draw): GenerationRequest {
            return new GenerationRequest(
                game: $draw->type,
                drawNumber: $draw->draw_number,
                context: DrawPagePrompt::context($draw),
                prompt: DrawPagePrompt::prompt($draw),
                schema: DrawPagePrompt::schema(),
            );
        });

        $batchId = $provider->submitBatch($requests);

        foreach ($draws as $draw) {
            Page::updateOrCreate(
                ['draw_id' => $draw->id],
                [
                    'draw_id' => $draw->id,
                    'title' => sprintf('Resultado %s concurso %d', $draw->game_name, $draw->draw_number),
                    'slug' => sprintf('%s/resultado/%d', $draw->type->value, $draw->draw_number),
                    'layout' => 'draw-page',
                    'blocks' => [],
                    'status' => PageStatus::Generating->value,
                    'batch_id' => $batchId,
                    'provider' => $driverName,
                    'generated_at' => null,
                ],
            );
        }

        $this->info(sprintf('Submitted batch %s for %d draws.', $batchId, $draws->count()));

        return Command::SUCCESS;
    }
}
