@aware(['page'])
@props(['title', 'lottery_type', 'limit', 'show_prizes', 'show_dates', 'link_to_details', 'results'])

<div class="bg-white rounded-lg shadow-lg p-6 mb-8">
    <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ $title }}</h2>
    
    @if($results && $results->count() > 0)
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach($results as $result)
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <!-- Game Title -->
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-lg text-gray-800 capitalize">
                            {{ str_replace('_', ' ', $result->game) }}
                        </h3>
                        @if($show_dates)
                            <span class="text-sm text-gray-500">
                                {{ $result->draw_date->format('d/m/Y') }}
                            </span>
                        @endif
                    </div>
                    
                    <!-- Draw Number -->
                    <div class="text-sm text-gray-600 mb-2">
                        Concurso: {{ $result->draw_number }}
                    </div>
                    
                    <!-- Numbers -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        @if($result->numbers)
                            @foreach(json_decode($result->numbers) as $number)
                                <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-600 text-white text-sm font-bold rounded-full">
                                    {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                    
                    <!-- Prize Information -->
                    @if($show_prizes && $result->estimated_prize)
                        <div class="text-sm">
                            <span class="text-gray-600">Prêmio estimado:</span>
                            <span class="font-semibold text-green-600">
                                R$ {{ number_format($result->estimated_prize, 2, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    
                    <!-- Link to Details -->
                    @if($link_to_details && $result->drawPage)
                        <div class="mt-4">
                            <a href="{{ url($result->drawPage->slug) }}" 
                               class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Ver detalhes
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2">
                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhum resultado encontrado.</p>
        </div>
    @endif
</div>
