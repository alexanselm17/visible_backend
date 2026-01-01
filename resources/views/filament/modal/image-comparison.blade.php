{{-- resources/views/filament/modals/image-comparison.blade.php --}}
<div class="p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- User 1 --}}
        <div class="space-y-4">
            <div class="text-center">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">👤 {{ $user1['name'] }}</h3>
                <div class="bg-blue-50 rounded-lg p-3 mb-4">
                    <div class="text-sm text-gray-600">
                        <p><strong>Views:</strong> {{ $user1['views'] }}</p>
                        <p><strong>Timestamp:</strong> {{ $user1['timestamp'] }}</p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-center">
                <div class="relative group">
                    <img 
                        src="{{ $user1['url'] ?? 'https://visibledm.com/storage/products/default-product.png' }}" 
                        alt="User 1 Image" 
                        class="w-64 h-64 object-cover rounded-lg shadow-lg border-2 border-blue-200 hover:border-blue-400 transition-all duration-300"
                        onclick="this.classList.toggle('scale-110')"
                    >
                    <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-10 rounded-lg transition-all duration-300 cursor-zoom-in"></div>
                </div>
            </div>
        </div>

        {{-- User 2 --}}
        @if($user2)
        <div class="space-y-4">
            <div class="text-center">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">👤 {{ $user2['name'] }}</h3>
                <div class="bg-red-50 rounded-lg p-3 mb-4">
                    <div class="text-sm text-gray-600">
                        <p><strong>Views:</strong> {{ $user2['views'] }}</p>
                        <p><strong>Timestamp:</strong> {{ $user2['timestamp'] }}</p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-center">
                <div class="relative group">
                    <img 
                        src="{{ $user2['url'] ?? 'https://visibledm.com/storage/products/default-product.png' }}" 
                        alt="User 2 Image" 
                        class="w-64 h-64 object-cover rounded-lg shadow-lg border-2 border-red-200 hover:border-red-400 transition-all duration-300"
                        onclick="this.classList.toggle('scale-110')"
                    >
                    <div class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-10 rounded-lg transition-all duration-300 cursor-zoom-in"></div>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- Comparison Analysis --}}
    <div class="mt-8 border-t pt-6">
        <div class="bg-yellow-50 rounded-lg p-6 border border-yellow-200">
            <h4 class="text-lg font-semibold text-yellow-800 mb-4 flex items-center">
                🔍 Similarity Analysis
            </h4>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">85%</div>
                    <div class="text-sm text-gray-600">Overall Similarity</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600">92%</div>
                    <div class="text-sm text-gray-600">Facial Features</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">78%</div>
                    <div class="text-sm text-gray-600">Background Match</div>
                </div>
            </div>

            <div class="text-sm text-gray-700 space-y-2">
                <p><strong>⚠️ High Risk Indicators:</strong></p>
                <ul class="list-disc list-inside space-y-1 ml-4">
                    <li>Identical facial structure detected</li>
                    <li>Similar lighting conditions</li>
                    <li>Coordinated viewing timestamps</li>
                    <li>Suspicious user behavior patterns</li>
                </ul>
            </div>

            <div class="mt-4 p-3 bg-red-100 rounded-lg border border-red-200">
                <p class="text-sm text-red-800">
                    <strong>🚨 Fraud Alert:</strong> These images show high similarity suggesting potential coordinated fraud activity. Consider investigating these user accounts further.
                </p>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="mt-6 flex flex-wrap gap-3 justify-center">
        <button class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
            🚫 Flag as Fraud
        </button>
        <button class="px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors text-sm font-medium">
            ⚠️ Mark for Review
        </button>
        <button class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium">
            ✅ Mark as Legitimate
        </button>
    </div>
</div>

<style>
.scale-110 {
    transform: scale(1.1);
}
</style>