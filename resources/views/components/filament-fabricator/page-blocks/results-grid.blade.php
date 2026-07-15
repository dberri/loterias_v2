@aware(['page'])
@props(['title', 'lottery_type', 'results_per_page', 'date_from', 'date_to', 'show_accumulated_only', 'enable_pagination', 'results'])

<div class="p-6 mb-8 bg-white rounded-lg shadow-lg">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
        <div class="text-sm text-gray-500 capitalize">
            {{ str_replace(['_', 'mega', 'sena'], [' ', 'Mega', 'Sena'], $lottery_type) }}
        </div>
    </div>
    
    @if($results && (is_countable($results) ? count($results) : $results->count()) > 0)
        <!-- Filters Info -->
        @if($date_from || $date_to || $show_accumulated_only)
            <div class="p-4 mb-6 border border-blue-200 rounded-lg bg-blue-50">
                <h3 class="mb-2 text-sm font-medium text-blue-800">Filtros aplicados:</h3>
                <div class="space-y-1 text-sm text-blue-700">
                    @if($date_from)
                        {{-- <div>De: {{ \Carbon\Carbon::parse($date_from)->format('d/m/Y') }}</div> --}}
                    @endif
                    @if($date_to)
                        {{-- <div>Até: {{ \Carbon\Carbon::parse($date_to)->format('d/m/Y') }}</div> --}}
                    @endif
                    @if($show_accumulated_only)
                        <div>Apenas sorteios acumulados</div>
                    @endif
                </div>
            </div>
        @endif
        
        <!-- Results Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @forelse((is_countable($results) ? $results : $results->items() ?? $results->all()) as $result)
                <div class="p-4 transition-shadow border border-gray-200 rounded-lg hover:shadow-md">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <div class="text-lg font-semibold text-gray-900">
                                {{ $result->draw_number }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{-- {{ $result->draw_date->format('d/m/Y') }} --}}
                            </div>
                        </div>
                        @if($result->is_accumulated)
                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-800 bg-red-100 rounded-full">
                                Acumulou
                            </span>
                        @endif
                    </div>
                    
                    <!-- Numbers -->
                    <div class="flex flex-wrap gap-1 mb-4">
                        @if($result->drawn_numbers)
                            @foreach($result->drawn_numbers as $number)
                                <span class="inline-flex items-center justify-center text-xs font-bold text-white bg-blue-600 rounded-full w-7 h-7">
                                    {{ $number }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                    
                    <!-- Prize Info -->
                    @if($result->main_prize)
                        <div class="mb-2 text-xs text-gray-600">
                            Prêmio: <span class="font-semibold text-green-600">R$ {{ number_format($result->main_prize, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    
                    <!-- Winners Info -->
                    @if($result->main_prize_winners && $result->main_prize_winners > 0)
                        <div class="mb-3 text-xs text-gray-600">
                            {{ $result->main_prize_winners }} ganhador{{ $result->main_prize_winners > 1 ? 'es' : '' }}
                        </div>
                    @endif
                    
                    <!-- Link to Details -->
                    @if($result->drawPage)
                        <a href="{{ url($result->drawPage->slug) }}" 
                           class="inline-flex items-center text-xs font-medium text-blue-600 hover:text-blue-800">
                            Ver detalhes
                            <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                </div>
            @empty
                <div class="py-8 text-center col-span-full">
                    <div class="mb-2 text-gray-400">
                        <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-gray-500">Nenhum resultado encontrado para os filtros aplicados.</p>
                </div>
            @endforelse
        </div>
        
        <!-- Pagination -->
        @if($enable_pagination && method_exists($results, 'links'))
            <div class="mt-6">
                {{ $results->links() }}
            </div>
        @endif
    @else
        <div class="py-8 text-center">
            <div class="mb-2 text-gray-400">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhum resultado disponível.</p>
        </div>
    @endif
</div>
