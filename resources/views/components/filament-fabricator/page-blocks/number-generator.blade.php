@aware(['page'])
@props(['title', 'description', 'default_lottery', 'allow_lottery_selection', 'show_statistics', 'save_generated_numbers', 'primary_color', 'lottery_configs', 'default_config'])

<div class="bg-white rounded-lg shadow-lg p-6 mb-8">
    <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $title }}</h2>
        @if($description)
            <p class="text-gray-600">{{ $description }}</p>
        @endif
    </div>
    
    <div id="number-generator" class="max-w-2xl mx-auto">
        <!-- Lottery Selection -->
        @if($allow_lottery_selection)
            <div class="mb-6">
                <label for="lottery-select" class="block text-sm font-medium text-gray-700 mb-2">
                    Escolha a Loteria:
                </label>
                <select id="lottery-select" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        onchange="changeLottery()">
                    @foreach($lottery_configs as $key => $config)
                        <option value="{{ $key }}" {{ $key === $default_lottery ? 'selected' : '' }}>
                            {{ $config['name'] }} - {{ $config['description'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
        
        <!-- Current Lottery Info -->
        <div id="lottery-info" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="text-center">
                <h3 id="lottery-name" class="text-lg font-semibold text-blue-900 mb-1">
                    {{ $default_config['name'] }}
                </h3>
                <p id="lottery-description" class="text-sm text-blue-700">
                    {{ $default_config['description'] }}
                </p>
            </div>
        </div>
        
        <!-- Generated Numbers Display -->
        <div class="mb-6">
            <div class="bg-gray-50 rounded-lg p-6 min-h-[120px] flex items-center justify-center">
                <div id="generated-numbers" class="text-center">
                    <div class="text-gray-500 mb-4">
                        <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        Clique no botão abaixo para gerar números
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Generate Button -->
        <div class="text-center mb-6">
            <button id="generate-btn" 
                    onclick="generateNumbers()"
                    style="background-color: {{ $primary_color ?? '#3B82F6' }}"
                    class="px-8 py-3 text-white font-semibold rounded-lg hover:opacity-90 transition-opacity duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                🎲 Gerar Números
            </button>
        </div>
        
        <!-- Statistics -->
        @if($show_statistics)
            <div id="statistics" class="bg-gray-50 rounded-lg p-4 mb-6 hidden">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Estatísticas dos Números Gerados:</h4>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-600">Menor número:</span>
                        <span id="min-number" class="font-medium"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Maior número:</span>
                        <span id="max-number" class="font-medium"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Soma total:</span>
                        <span id="sum-numbers" class="font-medium"></span>
                    </div>
                    <div>
                        <span class="text-gray-600">Média:</span>
                        <span id="avg-numbers" class="font-medium"></span>
                    </div>
                </div>
            </div>
        @endif
        
        <!-- Save Numbers -->
        @if($save_generated_numbers)
            <div id="save-section" class="text-center hidden">
                <button id="save-btn" 
                        onclick="saveNumbers()"
                        class="px-6 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    💾 Salvar Números
                </button>
            </div>
        @endif
        
        <!-- Saved Numbers (if any) -->
        @if($save_generated_numbers)
            <div id="saved-numbers" class="mt-6 hidden">
                <h4 class="text-sm font-semibold text-gray-800 mb-2">Números Salvos:</h4>
                <div id="saved-list" class="space-y-2"></div>
            </div>
        @endif
    </div>
    
    <!-- Hidden data for JavaScript -->
    <script type="application/json" id="lottery-data">
        @json($lottery_configs)
    </script>
    
    <script>
        let currentLottery = '{{ $default_lottery }}';
        let lotteryConfigs = JSON.parse(document.getElementById('lottery-data').textContent);
        let savedNumbers = JSON.parse(localStorage.getItem('saved-lottery-numbers') || '[]');
        
        function changeLottery() {
            const select = document.getElementById('lottery-select');
            currentLottery = select.value;
            const config = lotteryConfigs[currentLottery];
            
            document.getElementById('lottery-name').textContent = config.name;
            document.getElementById('lottery-description').textContent = config.description;
            
            // Clear previous numbers
            document.getElementById('generated-numbers').innerHTML = `
                <div class="text-gray-500 mb-4">
                    <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                    </svg>
                    Clique no botão abaixo para gerar números
                </div>
            `;
        }
        
        function generateNumbers() {
            const config = lotteryConfigs[currentLottery];
            const numbers = [];
            
            while (numbers.length < config.numbers_to_pick) {
                const randomNum = Math.floor(Math.random() * (config.max_number - config.min_number + 1)) + config.min_number;
                if (!numbers.includes(randomNum)) {
                    numbers.push(randomNum);
                }
            }
            
            numbers.sort((a, b) => a - b);
            
            displayNumbers(numbers);
            
            @if($show_statistics)
                showStatistics(numbers);
            @endif
            
            @if($save_generated_numbers)
                document.getElementById('save-section').classList.remove('hidden');
            @endif
        }
        
        function displayNumbers(numbers) {
            const container = document.getElementById('generated-numbers');
            const config = lotteryConfigs[currentLottery];
            
            container.innerHTML = `
                <div>
                    <h4 class="text-lg font-semibold text-gray-800 mb-4">${config.name}</h4>
                    <div class="flex flex-wrap justify-center gap-2">
                        ${numbers.map(num => `
                            <span class="inline-flex items-center justify-center w-10 h-10 bg-blue-600 text-white font-bold rounded-full text-sm">
                                ${String(num).padStart(2, '0')}
                            </span>
                        `).join('')}
                    </div>
                    <p class="text-sm text-gray-600 mt-3">
                        Números: ${numbers.join(', ')}
                    </p>
                </div>
            `;
        }
        
        @if($show_statistics)
        function showStatistics(numbers) {
            const min = Math.min(...numbers);
            const max = Math.max(...numbers);
            const sum = numbers.reduce((a, b) => a + b, 0);
            const avg = (sum / numbers.length).toFixed(1);
            
            document.getElementById('min-number').textContent = min;
            document.getElementById('max-number').textContent = max;
            document.getElementById('sum-numbers').textContent = sum;
            document.getElementById('avg-numbers').textContent = avg;
            
            document.getElementById('statistics').classList.remove('hidden');
        }
        @endif
        
        @if($save_generated_numbers)
        function saveNumbers() {
            const numbersText = document.querySelector('#generated-numbers p').textContent;
            const config = lotteryConfigs[currentLottery];
            
            const saved = {
                lottery: currentLottery,
                name: config.name,
                numbers: numbersText,
                date: new Date().toLocaleString('pt-BR')
            };
            
            savedNumbers.push(saved);
            localStorage.setItem('saved-lottery-numbers', JSON.stringify(savedNumbers));
            
            displaySavedNumbers();
            
            // Show success message
            const btn = document.getElementById('save-btn');
            const originalText = btn.textContent;
            btn.textContent = '✅ Salvo!';
            btn.classList.remove('bg-green-600', 'hover:bg-green-700');
            btn.classList.add('bg-green-800');
            
            setTimeout(() => {
                btn.textContent = originalText;
                btn.classList.remove('bg-green-800');
                btn.classList.add('bg-green-600', 'hover:bg-green-700');
            }, 2000);
        }
        
        function displaySavedNumbers() {
            if (savedNumbers.length === 0) return;
            
            const container = document.getElementById('saved-list');
            container.innerHTML = savedNumbers.slice(-5).reverse().map((saved, index) => `
                <div class="bg-white border border-gray-200 rounded p-3 text-sm">
                    <div class="flex justify-between items-start">
                        <div>
                            <strong>${saved.name}</strong><br>
                            ${saved.numbers}<br>
                            <span class="text-gray-500">${saved.date}</span>
                        </div>
                        <button onclick="removeSavedNumber(${savedNumbers.length - 1 - index})" 
                                class="text-red-500 hover:text-red-700">×</button>
                    </div>
                </div>
            `).join('');
            
            document.getElementById('saved-numbers').classList.remove('hidden');
        }
        
        function removeSavedNumber(index) {
            savedNumbers.splice(index, 1);
            localStorage.setItem('saved-lottery-numbers', JSON.stringify(savedNumbers));
            displaySavedNumbers();
        }
        
        // Initialize saved numbers display
        displaySavedNumbers();
        @endif
    </script>
</div>
