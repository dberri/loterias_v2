<?php

namespace App\DTOs;

final class GenerationResult
{
    private function __construct(
        public readonly string $customId,
        public readonly ?array $payload,
        public readonly bool $valid,
        public readonly ?string $failureReason,
    ) {}

    public static function valid(string $customId, array $payload): self
    {
        return new self(
            customId: $customId,
            payload: $payload,
            valid: true,
            failureReason: null,
        );
    }

    public static function invalid(
        string $customId,
        ?array $payload = null,
        ?string $failureReason = null,
    ): self {
        return new self(
            customId: $customId,
            payload: $payload,
            valid: false,
            failureReason: $failureReason,
        );
    }
}
