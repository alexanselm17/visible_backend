@php
    $sessions = \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $getRecord()->id)
        ->select('ip_address', 'user_agent', 'last_activity')->orderByDesc('last_activity')->limit(10)->get();
@endphp
<x-filament::section heading="Recent database sessions" description="Session identifiers, payloads, passwords and API secrets are never shown. File/Redis-backed browser sessions are not listed here.">
    <div class="space-y-4">
        @forelse($sessions as $session)
            <div>
                <p class="font-medium">{{ $session->ip_address ?: 'No IP recorded' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</p>
                <p class="text-sm text-gray-500">{{ $session->user_agent ?: 'No device information recorded' }}</p>
            </div>
        @empty
            <p class="text-gray-500">No database sessions recorded.</p>
        @endforelse
    </div>
</x-filament::section>
