@aware(['page'])
@props(['title', 'lottery_type', 'show_total_draws', 'show_total_winners', 'show_accumulated_count', 'show_biggest_prize', 'show_latest_draw', 'show_next_estimated', 'statistics'])

<div class="p-6 mb-8 bg-white rounded-lg shadow-lg">
    <h2 class="mb-6 text-2xl font-bold text-center text-gray-900">{{ $title }}</h2>
    
    @if(!empty($statistics))
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($statistics as $game => $stats)
                <div class="p-6 border border-blue-200 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100">
                    <h3 class="mb-4 text-lg font-semibold text-center text-blue-900">
                        {{ $stats['game_name'] }}
                    </h3>
                    
                    <div class="space-y-4">
                        @if($show_total_draws && isset($stats['total_draws']))
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Total de Sorteios:</span>
                                <span class="text-lg font-bold text-blue-800">{{ number_format($stats['total_draws']) }}</span>
                            </div>
                        @endif
                        
                        @if($show_total_winners && isset($stats['total_winners']))
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Total de Ganhadores:</span>
                                <span class="text-lg font-bold text-green-600">{{ number_format($stats['total_winners']) }}</span>
                            </div>
                        @endif
                        
                        @if($show_accumulated_count && isset($stats['accumulated_count']))
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Sorteios Acumulados:</span>
                                <span class="text-lg font-bold text-red-600">{{ number_format($stats['accumulated_count']) }}</span>
                            </div>
                        @endif
                        
                        @if($show_biggest_prize && isset($stats['biggest_prize']) && $stats['biggest_prize'] > 0)
                            <div class="p-3 bg-white border border-blue-200 rounded-lg">
                                <div class="mb-1 text-xs text-center text-gray-500">Maior Prêmio:</div>
                                <div class="text-lg font-bold text-center text-green-600">
                                    R$ {{ number_format($stats['biggest_prize'], 2, ',', '.') }}
                                </div>
                            </div>
                        @endif
                        
                        @if($show_latest_draw && isset($stats['latest_draw']) && $stats['latest_draw'])
                            <div class="p-3 bg-white border border-blue-200 rounded-lg">
                                <div class="mb-2 text-xs text-center text-gray-500">Último Sorteio:</div>
                                <div class="text-center">
                                    <div class="mb-1 text-sm font-medium text-gray-800">
                                        Concurso {{ $stats['latest_draw']->draw_number }}
                                    </div>
                                    <div class="mb-2 text-xs text-gray-600">
                                        {{-- {{ $stats['latest_draw']->draw_date->format('d/m/Y') }} --}}
                                    </div>
                                    @if($stats['latest_draw']->drawn_numbers)
                                        <div class="flex flex-wrap justify-center gap-1">
                                            @foreach(array_slice($stats['latest_draw']->drawn_numbers, 0, 6) as $number)
                                                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-blue-600 rounded-full">
                                                    {{ $number }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                        
                        @if($show_next_estimated && isset($stats['next_estimated']) && $stats['next_estimated'] > 0)
                            <div class="p-3 border border-yellow-200 rounded-lg bg-gradient-to-r from-yellow-50 to-yellow-100">
                                <div class="mb-1 text-xs text-center text-yellow-700">Próximo Prêmio Estimado:</div>
                                <div class="text-lg font-bold text-center text-yellow-800">
                                    R$ {{ number_format($stats['next_estimated'], 2, ',', '.') }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        
        <!-- Summary for all lotteries -->
        @if($lottery_type === 'all' && count($statistics) > 1)
            <div class="p-6 mt-8 border border-gray-200 rounded-lg bg-gray-50">
                <h3 class="mb-4 text-lg font-semibold text-center text-gray-900">Resumo Geral</h3>
                <div class="grid gap-4 text-center md:grid-cols-4">
                    @if($show_total_draws)
                        <div>
                            <div class="text-2xl font-bold text-blue-600">
                                {{ number_format(collect($statistics)->sum('total_draws')) }}
                            </div>
                            <div class="text-sm text-gray-600">Total de Sorteios</div>
                        </div>
                    @endif
                    
                    @if($show_total_winners)
                        <div>
                            <div class="text-2xl font-bold text-green-600">
                                {{ number_format(collect($statistics)->sum('total_winners')) }}
                            </div>
                            <div class="text-sm text-gray-600">Total de Ganhadores</div>
                        </div>
                    @endif
                    
                    @if($show_accumulated_count)
                        <div>
                            <div class="text-2xl font-bold text-red-600">
                                {{ number_format(collect($statistics)->sum('accumulated_count')) }}
                            </div>
                            <div class="text-sm text-gray-600">Sorteios Acumulados</div>
                        </div>
                    @endif
                    
                    @if($show_biggest_prize)
                        <div>
                            <div class="text-2xl font-bold text-purple-600">
                                R$ {{ number_format(collect($statistics)->max('biggest_prize'), 0, ',', '.') }}
                            </div>
                            <div class="text-sm text-gray-600">Maior Prêmio</div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
        
    @else
        <div class="py-8 text-center">
            <div class="mb-2 text-gray-400">
                <svg class="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhuma estatística disponível.</p>
        </div>
    @endif
</div>
