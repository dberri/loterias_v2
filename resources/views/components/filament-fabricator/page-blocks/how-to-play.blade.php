@aware(['page'])
@props(['title' => 'Como jogar', 'content' => null])

<div class="bg-white rounded-lg shadow-lg p-6 mb-8">
    @if($title)
        <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ $title }}</h2>
    @endif

    @if($content)
        <div class="prose prose-lg max-w-none text-gray-700">
            {!! $content !!}
        </div>
    @endif
</div>
