<x-filament-panels::page>
    <x-filament::section heading="Create a campaign or member report" description="Choose the report and reporting dates. You can also export filtered records directly from token and earnings tables.">
        <form wire:submit="download" class="space-y-6">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">Open report</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
