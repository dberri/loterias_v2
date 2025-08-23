@aware(['page'])
@props(['draw_id', 'show_prize_breakdown', 'show_winners_by_tier', 'show_statistics', 'show_comparison', 'custom_title', 'draw', 'previous_draw', 'number_frequency'])

@if($draw)
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <!-- Header -->
        <div class="border-b border-gray-200 pb-6 mb-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
                {{ $custom_title ?: "Resultado {$draw->game} - Concurso {$draw->draw_number}" }}
            </h1>
            <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600">
                <span class="flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ $draw->draw_date->format('d/m/Y') }}
                </span>
                @if($draw->accumulated)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Acumulou
                    </span>
                @endif
            </div>
        </div>
        
        <!-- Main Numbers -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Números Sorteados</h2>
            <div class="flex flex-wrap gap-3 justify-center md:justify-start">
                @if($draw->numbers)
                    @foreach(json_decode($draw->numbers) as $number)
                        <div class="flex items-center justify-center w-12 h-12 bg-blue-600 text-white text-lg font-bold rounded-full shadow-lg">
                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
        
        <!-- Prize Information -->
        @if($show_prize_breakdown && $draw->estimated_prize)
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Informações do Prêmio</h2>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <h3 class="font-medium text-green-800 mb-2">Prêmio Estimado</h3>
                            <div class="text-2xl font-bold text-green-600">
                                R$ {{ number_format($draw->estimated_prize, 2, ',', '.') }}
                            </div>
                        </div>
                        @if($draw->winners_count)
                            <div>
                                <h3 class="font-medium text-green-800 mb-2">Ganhadores</h3>
                                <div class="text-2xl font-bold text-green-600">
                                    {{ $draw->winners_count }}
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
        
        <!-- Winners by Tier (if available) -->
        @if($show_winners_by_tier && $draw->prize_distribution)
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Distribuição de Prêmios</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Faixa</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ganhadores</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prêmio Individual</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(json_decode($draw->prize_distribution, true) ?? [] as $tier)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                        {{ $tier['matches'] ?? 'N/A' }} acertos
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ number_format($tier['winners'] ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        R$ {{ number_format($tier['prize'] ?? 0, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
        
        <!-- Number Statistics -->
        @if($show_statistics && !empty($number_frequency))
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Estatísticas dos Números</h2>
                <div class="grid gap-6 md:grid-cols-2">
                    <!-- Most Frequent -->
                    <div>
                        <h3 class="font-medium text-gray-800 mb-3">Números Mais Sorteados</h3>
                        <div class="space-y-2">
                            @foreach(array_slice($number_frequency, 0, 10, true) as $number => $count)
                                <div class="flex items-center justify-between bg-gray-50 rounded px-3 py-2">
                                    <span class="font-medium">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-sm text-gray-600">{{ $count }}x</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    
                    <!-- Least Frequent -->
                    <div>
                        <h3 class="font-medium text-gray-800 mb-3">Números Menos Sorteados</h3>
                        <div class="space-y-2">
                            @foreach(array_slice(array_reverse($number_frequency, true), 0, 10, true) as $number => $count)
                                <div class="flex items-center justify-between bg-gray-50 rounded px-3 py-2">
                                    <span class="font-medium">{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="text-sm text-gray-600">{{ $count }}x</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif
        
        <!-- Comparison with Previous Draw -->
        @if($show_comparison && $previous_draw)
            <div class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Comparação com Sorteio Anterior</h2>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <h4 class="font-medium text-blue-800 mb-2">Concurso {{ $draw->draw_number }}</h4>
                            <div class="flex flex-wrap gap-1">
                                @foreach(json_decode($draw->numbers) as $number)
                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-blue-600 text-white text-sm font-bold rounded-full">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <h4 class="font-medium text-blue-800 mb-2">Concurso {{ $previous_draw->draw_number }}</h4>
                            <div class="flex flex-wrap gap-1">
                                @foreach(json_decode($previous_draw->numbers) as $number)
                                    <span class="inline-flex items-center justify-center w-8 h-8 bg-gray-500 text-white text-sm font-bold rounded-full">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    
                    @php
                        $currentNumbers = json_decode($draw->numbers, true) ?? [];
                        $previousNumbers = json_decode($previous_draw->numbers, true) ?? [];
                        $repeatedNumbers = array_intersect($currentNumbers, $previousNumbers);
                    @endphp
                    
                    @if(count($repeatedNumbers) > 0)
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <p class="text-sm text-blue-700">
                                <strong>{{ count($repeatedNumbers) }}</strong> número(s) se repetiu(ram): 
                                <span class="font-medium">{{ implode(', ', array_map(fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT), $repeatedNumbers)) }}</span>
                            </p>
                        </div>
                    @else
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <p class="text-sm text-blue-700">Nenhum número se repetiu do sorteio anterior.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
@else
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
        <div class="text-center py-8">
            <div class="text-gray-400 mb-2">
                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-gray-500">Nenhum sorteio selecionado.</p>
        </div>
    </div>
@endif
