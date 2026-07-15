@aware(['page'])
@props(['title', 'lottery_type', 'limit', 'show_prizes', 'show_dates', 'link_to_details', 'results'])

<div class="p-6 mb-8 bg-white rounded-lg shadow-lg">
    <h2 class="mb-6 text-2xl font-bold text-gray-900">{{ $title }}</h2>
    
    @if($results && $results->count() > 0)
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($results as $result)
                <div class="p-4 transition-shadow border border-gray-200 rounded-lg hover:shadow-md">
                    <!-- Game Title -->
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-semibold text-gray-800 capitalize">
                            {{ str_replace('_', ' ', $result->type->value) }}
                        </h3>
                        @if($show_dates)
                            <span class="text-sm text-gray-500">
                                {{-- {{ $result->draw_date->format('d/m/Y') }} --}}
                            </span>
                        @endif
                    </div>
                    
                    <!-- Draw Number -->
                    <div class="mb-2 text-sm text-gray-600">
                        Concurso: {{ $result->draw_number }}
                    </div>
                    
                    <!-- Numbers -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        @if($result->drawn_numbers)
                            @foreach($result->drawn_numbers as $number)
                                <span class="inline-flex items-center justify-center w-8 h-8 text-sm font-bold text-white bg-blue-600 rounded-full">
                                    {{ $number }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                    
                    <!-- Prize Information -->
                    @if($show_prizes && $result->main_prize)
                        <div class="text-sm">
                            <span class="text-gray-600">Prêmio estimado:</span>
                            <span class="font-semibold text-green-600">
                                R$ {{ number_format($result->main_prize, 2, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    
                    <!-- Link to Details -->
                    @if($link_to_details && $result->drawPage)
                        <div class="mt-4">
                            <a href="{{ url($result->drawPage->slug) }}" 
                               class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                                Ver detalhes
                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="py-8 text-center">
            <div class="mb-2 text-gray-400">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhum resultado encontrado.</p>
        </div>
    @endif
</div>
