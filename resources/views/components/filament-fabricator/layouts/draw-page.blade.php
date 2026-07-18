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

{{--
    Emitted inline rather than pushed to the base layout's `head` stack.

    @push('head') here silently produced NOTHING on CI -- zero script tags, the
    substring absent from the response entirely -- while passing on PHP 8.3, 8.4
    and 8.5 locally, with a cleared view cache and with CI's exact environment
    overlaid. The data was verified correct on CI at the point of render
    ($page->draw resolves, draw_date and game_name both populated), so the push
    content was being lost across the component boundary rather than never
    generated.

    Structured data is valid anywhere in the document -- Google reads JSON-LD in
    the body -- so the stack bought nothing here and cost the entire SEO payload
    on any machine where it misbehaves. Failing invisibly is the worst property
    this markup could have: the page still returns 200 and looks correct, while
    the structured data it exists to emit is simply absent.
--}}
<x-filament-fabricator::layouts.base :title="$page->title">
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

    <x-filament-fabricator::page-blocks :blocks="$page->blocks" />
</x-filament-fabricator::layouts.base>
