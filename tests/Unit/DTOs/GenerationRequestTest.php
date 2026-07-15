<?php

namespace Tests\Unit\DTOs;

use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use InvalidArgumentException;
use Tests\TestCase;

class GenerationRequestTest extends TestCase
{
    public function test_custom_id_round_trips_to_game_and_concurso(): void
    {
        $request = GenerationRequest::fromCustomId(
            'page_megasena_2500',
            context: ['foo' => 'bar'],
            prompt: 'prompt text',
            schema: ['type' => 'object'],
        );

        $this->assertSame('page_megasena_2500', $request->customId);
        $this->assertSame(GamesEnum::MEGA_SENA, $request->game);
        $this->assertSame(2500, $request->drawNumber);
        $this->assertSame(['foo' => 'bar'], $request->context);
        $this->assertSame('prompt text', $request->prompt);
        $this->assertSame(['type' => 'object'], $request->schema);
    }

    public function test_invalid_custom_id_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid generation request custom id [page_megasena].');

        GenerationRequest::fromCustomId('page_megasena');
    }

    public function test_generation_result_marks_valid_payloads(): void
    {
        $result = GenerationResult::valid('page_megasena_2500', [
            'title' => 'Resultado',
            'slug' => 'mega-sena/resultado/2500',
        ]);

        $this->assertTrue($result->valid);
        $this->assertSame('page_megasena_2500', $result->customId);
        $this->assertSame([
            'title' => 'Resultado',
            'slug' => 'mega-sena/resultado/2500',
        ], $result->payload);
        $this->assertNull($result->failureReason);
    }

    public function test_generation_result_marks_invalid_payloads(): void
    {
        $result = GenerationResult::invalid('page_megasena_2500', ['title' => '']);

        $this->assertFalse($result->valid);
        $this->assertSame('page_megasena_2500', $result->customId);
        $this->assertSame(['title' => ''], $result->payload);
        $this->assertNull($result->failureReason);
    }

    public function test_batch_status_has_the_expected_normalized_values(): void
    {
        $this->assertSame(
            ['in_progress', 'completed', 'failed', 'expired'],
            array_map(fn (BatchStatus $status): string => $status->value, BatchStatus::cases()),
        );
    }
}
