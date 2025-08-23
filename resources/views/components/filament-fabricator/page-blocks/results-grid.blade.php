@aware(['page'])
@props(['title', 'lottery_type', 'results_per_page', 'date_from', 'date_to', 'show_accumulated_only', 'enable_pagination', 'results'])

<div class="bg-white rounded-lg shadow-lg p-6 mb-8">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">{{ $title }}</h2>
        <div class="text-sm text-gray-500 capitalize">
            {{ str_replace(['_', 'mega', 'sena'], [' ', 'Mega', 'Sena'], $lottery_type) }}
        </div>
    </div>
    
    @if($results && (is_countable($results) ? count($results) : $results->count()) > 0)
        <!-- Filters Info -->
        @if($date_from || $date_to || $show_accumulated_only)
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="text-sm font-medium text-blue-800 mb-2">Filtros aplicados:</h3>
                <div class="text-sm text-blue-700 space-y-1">
                    @if($date_from)
                        <div>De: {{ \Carbon\Carbon::parse($date_from)->format('d/m/Y') }}</div>
                    @endif
                    @if($date_to)
                        <div>Até: {{ \Carbon\Carbon::parse($date_to)->format('d/m/Y') }}</div>
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
                <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                    <!-- Header -->
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="text-lg font-semibold text-gray-900">
                                {{ $result->draw_number }}
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $result->draw_date->format('d/m/Y') }}
                            </div>
                        </div>
                        @if($result->accumulated)
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                Acumulou
                            </span>
                        @endif
                    </div>
                    
                    <!-- Numbers -->
                    <div class="flex flex-wrap gap-1 mb-4">
                        @if($result->numbers)
                            @foreach(json_decode($result->numbers) as $number)
                                <span class="inline-flex items-center justify-center w-7 h-7 bg-blue-600 text-white text-xs font-bold rounded-full">
                                    {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endforeach
                        @endif
                    </div>
                    
                    <!-- Prize Info -->
                    @if($result->estimated_prize)
                        <div class="text-xs text-gray-600 mb-2">
                            Prêmio: <span class="font-semibold text-green-600">R$ {{ number_format($result->estimated_prize, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    
                    <!-- Winners Info -->
                    @if($result->winners_count && $result->winners_count > 0)
                        <div class="text-xs text-gray-600 mb-3">
                            {{ $result->winners_count }} ganhador{{ $result->winners_count > 1 ? 'es' : '' }}
                        </div>
                    @endif
                    
                    <!-- Link to Details -->
                    @if($result->drawPage)
                        <a href="{{ url($result->drawPage->slug) }}" 
                           class="inline-flex items-center text-blue-600 hover:text-blue-800 text-xs font-medium">
                            Ver detalhes
                            <svg class="ml-1 w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                </div>
            @empty
                <div class="col-span-full text-center py-8">
                    <div class="text-gray-400 mb-2">
                        <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2">
                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhum resultado disponível.</p>
        </div>
    @endif
</div>
