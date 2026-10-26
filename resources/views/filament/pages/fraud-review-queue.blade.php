<x-filament-panels::page>
    <x-filament::section heading="Find matching screenshot patterns" description="Matching views and time are review signals. Inspect the screenshots before making a decision.">
        <form wire:submit="scan" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass">Find activity to review</x-filament::button>
        </form>
    </x-filament::section>
    @if($scanned && empty($groups))
        <x-filament::section><p>No unreviewed matching patterns for this campaign and view threshold.</p></x-filament::section>
    @endif
    @foreach($groups as $group)
        <x-filament::section :heading="$group['advert_name'] ?? 'Advert'" :description="'Matching views & time: '.$group['matching_views_timestamp']">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px">
                @foreach($group['details'] as $detail)
                    <div>
                        <p class="font-semibold">{{ $detail['name'] ?? 'Member' }}</p>
                        <p class="text-sm text-gray-500">{{ $detail['views'] }} views · screenshot {{ $detail['number'] }}</p>
                        <a href="{{ $detail['url'] }}" target="_blank" rel="noopener"><img src="{{ $detail['url'] }}" alt="Screenshot evidence" style="width:100%;height:240px;object-fit:contain;margin:12px 0"></a>
                    </div>
                @endforeach
            </div>
            <div class="mt-4 flex gap-3">
                <x-filament::button wire:click="mountAction('review', @js(['advert' => $group['advert_id'], 'pattern' => $group['matching_views_timestamp']]))">Record review</x-filament::button>
                <x-filament::button color="gray" wire:click="mountAction('member', @js(['advert' => $group['advert_id'], 'pattern' => $group['matching_views_timestamp']]))">Warn / deactivate members</x-filament::button>
            </div>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
