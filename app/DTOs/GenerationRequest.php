<?php

namespace App\DTOs;

use App\Enums\GamesEnum;
use InvalidArgumentException;

final class GenerationRequest
{
    public readonly string $customId;

    public function __construct(
        public readonly GamesEnum $game,
        public readonly int $drawNumber,
        public readonly array $context,
        public readonly string $prompt,
        public readonly array $schema,
    ) {
        $this->customId = self::buildCustomId($this->game, $this->drawNumber);
    }

    public static function fromCustomId(
        string $customId,
        array $context = [],
        string $prompt = '',
        array $schema = [],
    ): self {
        if (! preg_match('/^page_([a-z0-9-]+)_(\d+)$/', $customId, $matches)) {
            throw new InvalidArgumentException("Invalid generation request custom id [{$customId}].");
        }

        $game = GamesEnum::tryFrom($matches[1]);

        if (! $game) {
            throw new InvalidArgumentException("Invalid generation request game [{$matches[1]}].");
        }

        return new self(
            game: $game,
            drawNumber: (int) $matches[2],
            context: $context,
            prompt: $prompt,
            schema: $schema,
        );
    }

    public static function buildCustomId(GamesEnum $game, int $drawNumber): string
    {
        return sprintf('page_%s_%d', $game->value, $drawNumber);
    }
}
