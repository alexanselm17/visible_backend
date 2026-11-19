<div class="space-y-4 text-sm">
    @forelse ($events as $event)
        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
            <p class="font-semibold">#{{ $event->sequence }} · {{ str_replace('_', ' ', $event->event_type) }}</p>
            <p class="text-xs text-gray-500">{{ $event->created_at->timezone('Africa/Nairobi')->format('Y-m-d H:i:s') }} EAT · {{ $event->actor_type }} {{ $event->actor_id }}</p>
            @if ($event->token_purchase_id)<p>Purchase: {{ $event->token_purchase_id }}</p>@endif
            <pre class="mt-2 whitespace-pre-wrap break-all text-xs">{{ json_encode($event->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @if ($event->evidence)
                <p class="mt-2 font-medium">Conflicting authenticated bank fields (original receipt unchanged)</p>
                <pre class="whitespace-pre-wrap break-all text-xs">{{ json_encode($event->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @endif
        </div>
    @empty
        <p class="text-gray-500">Historical record: no new audit events have been recorded yet.</p>
    @endforelse
</div>
