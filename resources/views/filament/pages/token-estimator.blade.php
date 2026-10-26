<x-filament-panels::page>
    <x-filament::section heading="Check the cost before committing" description="Advert cost is the submission fee plus reach tokens. Video starts at 5, image at 3 and text at 1. This estimator does not charge a wallet or create a purchase.">
        <form wire:submit="calculate" class="space-y-5">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-calculator">Calculate quote</x-filament::button>
        </form>
    </x-filament::section>
    @if($quote)
        <x-filament::section heading="Your quote">
            <dl class="space-y-4">
                @foreach($quote as $label => $value)
                    <div class="flex justify-between gap-6"><dt class="text-gray-500">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-filament::section>
    @endif
</x-filament-panels::page>
