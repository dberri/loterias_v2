@props(['page'])

@php
    $draw = $page->draw;
    $blocks = collect($page->blocks ?? []);
    $faqBlock = $blocks->firstWhere('type', 'faq');
    $faqItems = data_get($faqBlock, 'data.faqs', []);

    $articleJsonLd = $draw ? [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => sprintf('Resultado %s concurso %d', $draw->game_name, $draw->draw_number),
        'description' => sprintf('Resultado do concurso %d da %s.', $draw->draw_number, $draw->game_name),
        'datePublished' => $draw->draw_date?->toAtomString(),
        'dateModified' => $page->generated_at?->toAtomString(),
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => url("/{$draw->type->value}/resultado/{$draw->draw_number}"),
        ],
        'articleSection' => $draw->game_name,
        'keywords' => implode(', ', array_merge([$draw->game_name, (string) $draw->draw_number], $draw->drawn_numbers)),
    ] : null;

    $faqJsonLd = filled($faqItems) ? [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($faqItems)->map(function (array $faq): array {
            $question = trim((string) data_get($faq, 'question'));
            $answer = trim(strip_tags((string) data_get($faq, 'answer')));

            return [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        })->filter(fn (array $faq): bool => $faq['name'] !== '' && $faq['acceptedAnswer']['text'] !== '')->values()->all(),
    ] : null;
@endphp

@push('head')
    @if($articleJsonLd)
        <script type="application/ld+json">
            {!! json_encode($articleJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif

    @if($faqJsonLd && ! empty($faqJsonLd['mainEntity']))
        <script type="application/ld+json">
            {!! json_encode($faqJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
@endpush

<x-filament-fabricator::layouts.base :title="$page->title">
    <x-filament-fabricator::page-blocks :blocks="$page->blocks" />
</x-filament-fabricator::layouts.base>
