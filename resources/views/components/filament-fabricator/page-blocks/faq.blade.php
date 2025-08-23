@aware(['page'])
@props(['title', 'layout_style', 'category', 'faqs'])

<div class="bg-white rounded-lg shadow-lg p-6 mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">{{ $title }}</h2>
    
    @if(!empty($faqs))
        @if($layout_style === 'accordion')
            <!-- Accordion Layout -->
            <div class="space-y-4">
                @foreach($faqs as $index => $faq)
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <button type="button"
                                class="w-full px-6 py-4 text-left bg-gray-50 hover:bg-gray-100 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-inset"
                                onclick="toggleFaq({{ $index }})"
                                aria-expanded="false"
                                aria-controls="faq-{{ $index }}">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-medium text-gray-900 pr-4">
                                    {{ $faq['question'] }}
                                </h3>
                                <svg class="flex-shrink-0 w-5 h-5 text-gray-500 transform transition-transform duration-200"
                                     id="icon-{{ $index }}"
                                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </div>
                        </button>
                        <div id="faq-{{ $index }}"
                             class="hidden px-6 py-4 bg-white border-t border-gray-200">
                            <div class="prose prose-sm max-w-none text-gray-700">
                                {!! $faq['answer'] !!}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            
            <script>
                function toggleFaq(index) {
                    const content = document.getElementById('faq-' + index);
                    const icon = document.getElementById('icon-' + index);
                    const button = content.previousElementSibling;
                    
                    if (content.classList.contains('hidden')) {
                        content.classList.remove('hidden');
                        icon.style.transform = 'rotate(180deg)';
                        button.setAttribute('aria-expanded', 'true');
                    } else {
                        content.classList.add('hidden');
                        icon.style.transform = 'rotate(0deg)';
                        button.setAttribute('aria-expanded', 'false');
                    }
                }
            </script>
            
        @elseif($layout_style === 'grid')
            <!-- Grid Layout -->
            <div class="grid gap-6 md:grid-cols-2">
                @foreach($faqs as $faq)
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">
                            {{ $faq['question'] }}
                        </h3>
                        <div class="prose prose-sm max-w-none text-gray-700">
                            {!! $faq['answer'] !!}
                        </div>
                    </div>
                @endforeach
            </div>
            
        @else
            <!-- List Layout -->
            <div class="space-y-8">
                @foreach($faqs as $faq)
                    <div class="border-b border-gray-200 pb-6 last:border-b-0 last:pb-0">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">
                            {{ $faq['question'] }}
                        </h3>
                        <div class="prose prose-sm max-w-none text-gray-700">
                            {!! $faq['answer'] !!}
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        
        <!-- Category Badge -->
        @if($category && $category !== 'general')
            <div class="mt-6 text-center">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    @switch($category)
                        @case('megasena')
                            Mega Sena
                            @break
                        @case('lotofacil')
                            Lotofácil
                            @break
                        @case('quina')
                            Quina
                            @break
                        @case('prizes')
                            Prêmios
                            @break
                        @case('how_to_play')
                            Como Jogar
                            @break
                        @case('technical')
                            Técnico
                            @break
                        @default
                            {{ ucfirst($category) }}
                    @endswitch
                </span>
            </div>
        @endif
        
    @else
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2">
                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhuma pergunta frequente configurada.</p>
        </div>
    @endif
</div>
