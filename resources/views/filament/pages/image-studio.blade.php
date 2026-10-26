<x-filament-panels::page>
    <x-filament::section heading="Create a personalized posting image" description="Preview the available layouts with the actual creative. Each generated image carries the selected member’s ownership reference.">
        <form wire:submit="generate" class="space-y-5">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-sparkles">Generate preview</x-filament::button>
        </form>
    </x-filament::section>
    @if($previewUrl)
        <x-filament::section heading="Your posting image">
            <img src="{{ $previewUrl }}" alt="Personalized advert layout preview" style="max-width:100%;max-height:850px;object-fit:contain;margin:0 auto;border-radius:16px">
            <div class="mt-4"><x-filament::button tag="a" :href="$previewUrl" target="_blank" icon="heroicon-o-arrow-down-tray">Open full image</x-filament::button></div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
