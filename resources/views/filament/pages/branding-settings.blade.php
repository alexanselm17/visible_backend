<x-filament-panels::page>
    <x-filament::section>
        <p>Start here, then open <strong>Legal policies</strong> to edit, preview and publish each document. No policy is published automatically.</p>
        <p style="margin-top: .5rem">The public request inbox is under <strong>Settings → Privacy requests</strong>. Staff must monitor it and actually fulfill verified requests.</p>
    </x-filament::section>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}
        <x-filament::button type="submit">Save branding & legal settings</x-filament::button>
    </form>
</x-filament-panels::page>
