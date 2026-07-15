@aware(['page'])
@props(['draw', 'related_links' => []])

@php
    $previousLink = data_get($related_links, 'previous');
    $nextLink = data_get($related_links, 'next');
    $pillarLink = data_get($related_links, 'pillar');
    $siblingLinks = data_get($related_links, 'siblings', []);
@endphp

<div class="px-4 py-4 md:py-8">
    <div class="mx-auto max-w-7xl rounded-2xl border border-slate-200 bg-white p-6 shadow-lg">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-slate-900">Links relacionados</h2>
            <p class="mt-1 text-sm text-slate-600">Navegação interna baseada em fatos do concurso e páginas publicadas.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @if($previousLink)
                <a href="{{ $previousLink['url'] }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-amber-400 hover:bg-amber-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $previousLink['label'] }}</div>
                    <div class="mt-2 text-sm font-medium text-slate-900">{{ $previousLink['title'] }}</div>
                </a>
            @endif

            @if($nextLink)
                <a href="{{ $nextLink['url'] }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-amber-400 hover:bg-amber-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $nextLink['label'] }}</div>
                    <div class="mt-2 text-sm font-medium text-slate-900">{{ $nextLink['title'] }}</div>
                </a>
            @endif

            @if($pillarLink)
                <a href="{{ $pillarLink['url'] }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-amber-400 hover:bg-amber-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $pillarLink['label'] }}</div>
                    <div class="mt-2 text-sm font-medium text-slate-900">{{ $pillarLink['title'] }}</div>
                </a>
            @endif

            @foreach($siblingLinks as $siblingLink)
                <a href="{{ $siblingLink['url'] }}" class="rounded-xl border border-slate-200 p-4 transition hover:border-amber-400 hover:bg-amber-50">
                    <div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Jogo irmão</div>
                    <div class="mt-2 text-sm font-medium text-slate-900">{{ $siblingLink['title'] }}</div>
                </a>
            @endforeach
        </div>

        @if(! $previousLink && ! $nextLink && ! $pillarLink && empty($siblingLinks))
            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-500">
                Nenhum link relacionado publicado no momento.
            </div>
        @endif
    </div>
</div>
