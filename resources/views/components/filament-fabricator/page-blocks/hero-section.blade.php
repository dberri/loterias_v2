@aware(['page'])
@props(['title', 'subtitle', 'description', 'primary_cta_text', 'primary_cta_url', 'secondary_cta_text', 'secondary_cta_url', 'background_style', 'background_image', 'text_alignment', 'show_lottery_highlights', 'height', 'latest_results'])

@php
    $backgroundClasses = [
        'gradient-blue' => 'bg-gradient-to-br from-blue-600 via-blue-700 to-blue-800',
        'gradient-green' => 'bg-gradient-to-br from-green-600 via-green-700 to-green-800',
        'gradient-purple' => 'bg-gradient-to-br from-purple-600 via-purple-700 to-purple-800',
        'solid-blue' => 'bg-blue-600',
        'solid-green' => 'bg-green-600',
        'image' => 'bg-cover bg-center bg-no-repeat',
    ];
    
    $heightClasses = [
        'small' => 'min-h-[300px]',
        'medium' => 'min-h-[400px]',
        'large' => 'min-h-[500px]',
        'full' => 'min-h-screen',
    ];
    
    $textAlignmentClasses = [
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
    ];
@endphp

<section class="relative {{ $backgroundClasses[$background_style] ?? $backgroundClasses['gradient-blue'] }} {{ $heightClasses[$height] ?? $heightClasses['medium'] }} flex items-center justify-center"
         @if($background_style === 'image' && $background_image)
             style="background-image: url('{{ Storage::url($background_image) }}')"
         @endif>
    
    <!-- Overlay for better text readability -->
    @if($background_style === 'image')
        <div class="absolute inset-0 bg-black bg-opacity-50"></div>
    @endif
    
    <div class="relative z-10 container mx-auto px-4 py-12">
        <div class="max-w-4xl mx-auto {{ $textAlignmentClasses[$text_alignment] ?? $textAlignmentClasses['center'] }}">
            
            <!-- Main Content -->
            <div class="text-white mb-8">
                @if($title)
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-4 leading-tight">
                        {{ $title }}
                    </h1>
                @endif
                
                @if($subtitle)
                    <h2 class="text-xl md:text-2xl lg:text-3xl font-light mb-6 text-blue-100">
                        {{ $subtitle }}
                    </h2>
                @endif
                
                @if($description)
                    <p class="text-lg md:text-xl mb-8 text-blue-50 max-w-2xl {{ $text_alignment === 'center' ? 'mx-auto' : '' }}">
                        {{ $description }}
                    </p>
                @endif
            </div>
            
            <!-- Call to Action Buttons -->
            @if($primary_cta_text && $primary_cta_url)
                <div class="flex flex-col sm:flex-row gap-4 {{ $text_alignment === 'center' ? 'justify-center' : ($text_alignment === 'right' ? 'justify-end' : 'justify-start') }} mb-8">
                    <a href="{{ $primary_cta_url }}" 
                       class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-md text-blue-700 bg-white hover:bg-blue-50 transition-colors duration-200 shadow-lg">
                        {{ $primary_cta_text }}
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                    
                    @if($secondary_cta_text && $secondary_cta_url)
                        <a href="{{ $secondary_cta_url }}" 
                           class="inline-flex items-center justify-center px-8 py-3 border-2 border-white text-base font-medium rounded-md text-white hover:bg-white hover:text-blue-700 transition-colors duration-200">
                            {{ $secondary_cta_text }}
                        </a>
                    @endif
                </div>
            @endif
            
            <!-- Lottery Highlights -->
            @if($show_lottery_highlights && !empty($latest_results))
                <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-lg p-6 border border-white border-opacity-20">
                    <h3 class="text-white text-lg font-semibold mb-4 text-center">Últimos Resultados</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        @foreach($latest_results as $game => $result)
                            <div class="text-center">
                                <div class="text-white text-sm font-medium mb-2 capitalize">
                                    {{ str_replace(['_', 'mega', 'sena'], [' ', 'Mega', 'Sena'], $game) }}
                                </div>
                                <div class="text-white text-xs mb-2">
                                    Concurso {{ $result->draw_number }}
                                </div>
                                <div class="flex justify-center gap-1 flex-wrap">
                                    @if($result->drawn_numbers)
                                        @foreach(array_slice($result->drawn_numbers, 0, 6) as $number)
                                            <span class="inline-flex items-center justify-center w-6 h-6 bg-white text-blue-700 text-xs font-bold rounded-full">
                                                {{ $number }}
                                            </span>
                                        @endforeach
                                    @endif
                                </div>
                                @if($result->main_prize)
                                    <div class="text-white text-xs mt-2">
                                        R$ {{ number_format($result->main_prize, 0, ',', '.') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
    
    <!-- Decorative Elements -->
    <div class="absolute bottom-0 left-0 right-0">
        <svg class="w-full h-16 text-white" fill="currentColor" viewBox="0 0 1200 120" preserveAspectRatio="none">
            <path d="M0,0V46.29c47.79,22.2,103.59,32.17,158,28,70.36-5.37,136.33-33.31,206.8-37.5C438.64,32.43,512.34,53.67,583,72.05c69.27,18,138.3,24.88,209.4,13.08,36.15-6,69.85-17.84,104.45-29.34C989.49,25,1113-14.29,1200,52.47V0Z" opacity=".25"></path>
            <path d="M0,0V15.81C13,36.92,27.64,56.86,47.69,72.05,99.41,111.27,165,111,224.58,91.58c31.15-10.15,60.09-26.07,89.67-39.8,40.92-19,84.73-46,130.83-49.67,36.26-2.85,70.9,9.42,98.6,31.56,31.77,25.39,62.32,62,103.63,73,40.44,10.79,81.35-6.69,119.13-24.28s75.16-39,116.92-43.05c59.73-5.85,113.28,22.88,168.9,38.84,30.2,8.66,59,6.17,87.09-7.5,22.43-10.89,48-26.93,60.65-49.24V0Z" opacity=".5"></path>
            <path d="M0,0V5.63C149.93,59,314.09,71.32,475.83,42.57c43-7.64,84.23-20.12,127.61-26.46,59-8.63,112.48,12.24,165.56,35.4C827.93,77.22,886,95.24,951.2,90c86.53-7,172.46-45.71,248.8-84.81V0Z"></path>
        </svg>
    </div>
</section>
