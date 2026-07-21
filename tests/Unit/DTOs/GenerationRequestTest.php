<?php

namespace Tests\Unit\DTOs\GenerationRequestTest;

use App\DTOs\BatchStatus;
use App\DTOs\GenerationRequest;
use App\DTOs\GenerationResult;
use App\Enums\GamesEnum;
use InvalidArgumentException;

test('custom id round trips to game and concurso', function () {
    $request = GenerationRequest::fromCustomId(
        'page_megasena_2500',
        context: ['foo' => 'bar'],
        prompt: 'prompt text',
        schema: ['type' => 'object'],
    );

    expect($request->customId)->toBe('page_megasena_2500');
    expect($request->game)->toBe(GamesEnum::MEGA_SENA);
    expect($request->drawNumber)->toBe(2500);
    expect($request->context)->toBe(['foo' => 'bar']);
    expect($request->prompt)->toBe('prompt text');
    expect($request->schema)->toBe(['type' => 'object']);
});

test('invalid custom id is rejected', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid generation request custom id [page_megasena].');

    GenerationRequest::fromCustomId('page_megasena');
});

test('generation result marks valid payloads', function () {
    $result = GenerationResult::valid('page_megasena_2500', [
        'title' => 'Resultado',
        'slug' => 'mega-sena/resultado/2500',
    ]);

    expect($result->valid)->toBeTrue();
    expect($result->customId)->toBe('page_megasena_2500');
    expect($result->payload)->toBe([
        'title' => 'Resultado',
        'slug' => 'mega-sena/resultado/2500',
    ]);
    expect($result->failureReason)->toBeNull();
});

test('generation result marks invalid payloads', function () {
    $result = GenerationResult::invalid('page_megasena_2500', ['title' => '']);

    expect($result->valid)->toBeFalse();
    expect($result->customId)->toBe('page_megasena_2500');
    expect($result->payload)->toBe(['title' => '']);
    expect($result->failureReason)->toBeNull();
});

test('batch status has the expected normalized values', function () {
    expect(array_map(fn (BatchStatus $status): string => $status->value, BatchStatus::cases()))->toBe(['in_progress', 'completed', 'failed', 'expired']);
});
