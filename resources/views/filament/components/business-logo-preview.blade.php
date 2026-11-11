<div class="space-y-4">
    @if ($url)
        <div class="overflow-hidden rounded-xl border border-gray-200 p-6 dark:border-gray-700"
            style="background: repeating-conic-gradient(#e5e7eb 0% 25%, #f9fafb 0% 50%) 50% / 24px 24px;">
            <img src="{{ $url }}" alt="{{ $logo->profile?->business_name ?: 'Business' }} logo"
                style="display: block; width: 100%; height: min(55vh, 480px); object-fit: contain;">
        </div>
        <p class="text-sm text-gray-500 dark:text-gray-400">The checked background shows transparent areas and is not part of the logo.</p>
        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
            class="text-sm font-medium text-primary-600 underline dark:text-primary-400">Open original image</a>
    @else
        <div class="rounded-xl border border-gray-200 p-6 text-center dark:border-gray-700">
            <p class="font-medium text-gray-950 dark:text-white">Logo preview is unavailable.</p>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">The image file could not be found. Upload the logo again from the campaign owner's profile.</p>
        </div>
    @endif
</div>
