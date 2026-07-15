<?php

namespace App\Services;

use App\DTOs\GenerationResult;
use App\Enums\PageStatus as PageStatusEnum;
use App\Models\Draw;
use App\Models\Page;
use App\Services\Content\DrawPagePrompt;
use Illuminate\Support\Facades\Log;

class PageAssembler
{
    public function assemble(Draw $draw, GenerationResult $result): Page
    {
        $error = $this->validationError($result);

        if ($error) {
            return $this->markFailed($draw, $result, $error);
        }

        return $this->persistPage($draw, $result->payload);
    }

    private function persistPage(Draw $draw, array $payload): Page
    {
        return Page::updateOrCreate(
            ['draw_id' => $draw->id],
            [
                'draw_id' => $draw->id,
                'title' => $payload['title'],
                'slug' => $payload['slug'],
                'layout' => 'default',
                'blocks' => $this->buildBlocks($draw, $payload),
                'status' => config('content.auto_publish', false)
                    ? PageStatusEnum::Published->value
                    : PageStatusEnum::Generated->value,
                'generated_at' => now(),
            ],
        );
    }

    private function markFailed(Draw $draw, GenerationResult $result, string $reason): Page
    {
        Log::warning('Draw page generation failed.', [
            'custom_id' => $result->customId,
            'draw_id' => $draw->id,
            'game' => $draw->type->value,
            'draw_number' => $draw->draw_number,
            'reason' => $reason,
        ]);

        return Page::updateOrCreate(
            ['draw_id' => $draw->id],
            [
                'draw_id' => $draw->id,
                'title' => $this->failedTitle($draw),
                'slug' => $this->pageSlug($draw),
                'layout' => 'default',
                'blocks' => [],
                'status' => PageStatusEnum::Failed->value,
                'generated_at' => null,
            ],
        );
    }

    private function validationError(GenerationResult $result): ?string
    {
        if (! $result->valid || ! is_array($result->payload)) {
            return $result->failureReason ?: 'Malformed JSON response.';
        }

        $payload = $result->payload;

        foreach (['title', 'slug', 'meta_description', 'enrichment_blocks'] as $field) {
            if (! array_key_exists($field, $payload)) {
                return "Missing required {$field}.";
            }
        }

        foreach (['title', 'slug', 'meta_description'] as $field) {
            if (! is_string($payload[$field]) || trim($payload[$field]) === '') {
                return "Missing required {$field}.";
            }
        }

        if (! is_array($payload['enrichment_blocks'])) {
            return 'Missing required enrichment_blocks array.';
        }

        $seenTypes = [];

        foreach ($payload['enrichment_blocks'] as $block) {
            if (! is_array($block)) {
                return 'Malformed enrichment block payload.';
            }

            $type = $block['type'] ?? null;

            if (! is_string($type) || ! in_array($type, DrawPagePrompt::enrichmentTypes(), true)) {
                return 'Unknown or disallowed enrichment block type.';
            }

            if (isset($seenTypes[$type])) {
                return "Duplicate enrichment block type [{$type}].";
            }

            $seenTypes[$type] = true;

            $blockError = match ($type) {
                'rich-text', 'how-to-play' => $this->validateHtmlBlock($block),
                'hot-cold-analysis', 'comparison-previous' => $this->validateCommentaryBlock($block),
                'faq' => $this->validateFaqBlock($block),
                default => 'Unknown or disallowed enrichment block type.',
            };

            if ($blockError) {
                return $blockError;
            }
        }

        return null;
    }

    private function validateHtmlBlock(array $block): ?string
    {
        $content = $block['html'] ?? $block['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            return 'Missing required prose.';
        }

        return null;
    }

    private function validateCommentaryBlock(array $block): ?string
    {
        $content = $block['commentary'] ?? $block['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            return 'Missing required prose.';
        }

        return null;
    }

    private function validateFaqBlock(array $block): ?string
    {
        $items = $block['items'] ?? $block['faqs'] ?? null;

        if (! is_array($items) || $items === []) {
            return 'Missing required prose.';
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                return 'Missing required prose.';
            }

            if (! is_string($item['q'] ?? $item['question'] ?? null) || trim((string) ($item['q'] ?? $item['question'] ?? '')) === '') {
                return 'Missing required prose.';
            }

            if (! is_string($item['a'] ?? $item['answer'] ?? null) || trim((string) ($item['a'] ?? $item['answer'] ?? '')) === '') {
                return 'Missing required prose.';
            }
        }

        return null;
    }

    private function buildBlocks(Draw $draw, array $payload): array
    {
        $blocks = [
            $this->heroBlock($draw, $payload),
            [
                'type' => 'results-grid',
                'data' => ['draw_id' => $draw->id],
            ],
            [
                'type' => 'individual-draw-details',
                'data' => ['draw_id' => $draw->id],
            ],
        ];

        foreach ($payload['enrichment_blocks'] as $block) {
            $blocks[] = $this->mapEnrichmentBlock($draw, $block);
        }

        $blocks[] = [
            'type' => 'related-links',
            'data' => ['draw_id' => $draw->id],
        ];

        return $blocks;
    }

    private function heroBlock(Draw $draw, array $payload): array
    {
        return [
            'type' => 'hero-section',
            'data' => [
                'draw_id' => $draw->id,
                'headline' => $payload['title'],
                'subtitle' => $payload['meta_description'],
            ],
        ];
    }

    private function mapEnrichmentBlock(Draw $draw, array $block): array
    {
        $type = $block['type'];
        $mappedType = match ($type) {
            'rich-text' => 'rich-text-content',
            'hot-cold-analysis' => 'statistics-cards',
            'comparison-previous' => 'comparison-table',
            'faq' => 'faq',
            'how-to-play' => 'how-to-play',
        };

        $data = array_merge(['draw_id' => $draw->id], $block);
        unset($data['type']);

        if ($type === 'faq') {
            $data['faqs'] = $data['items'] ?? $data['faqs'] ?? [];
            unset($data['items']);
        }

        if (in_array($type, ['rich-text', 'how-to-play'], true)) {
            $data['content'] = $data['html'] ?? $data['content'] ?? '';
            unset($data['html']);
        }

        if (in_array($type, ['hot-cold-analysis', 'comparison-previous'], true)) {
            $data['content'] = $data['commentary'] ?? $data['content'] ?? '';
            unset($data['commentary']);
        }

        return [
            'type' => $mappedType,
            'data' => $data,
        ];
    }

    private function failedTitle(Draw $draw): string
    {
        return sprintf('Resultado %s concurso %d', $draw->game_name, $draw->draw_number);
    }

    private function pageSlug(Draw $draw): string
    {
        return sprintf('%s/resultado/%d', $draw->type->value, $draw->draw_number);
    }
}
