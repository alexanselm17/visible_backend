@php
    $user = $getRecord();
    $wallets = $user->tokenWallets()->with('tokenType')->get();
    $balances = $user->rewardLedgerEntries()->selectRaw('currency, SUM(amount_minor) as total')->groupBy('currency')->get();
    $paid = $user->rewardPayouts()->where('status', 'paid')->selectRaw('currency, SUM(amount_minor) as total')->groupBy('currency')->get();
    $periods = $user->rewardPeriods()->with('plan')->latest('starts_at')->limit(5)->get();
    $frequency = app(\App\Services\RewardPeriodService::class)->frequencyFor($user->id, \Illuminate\Support\Carbon::now(config('rewards.timezone')));
    $next = $user->rewardPayoutPreferences()->where('effective_from', '>', now())->orderBy('effective_from')->first();
@endphp
<div class="space-y-6">
    <x-filament::section heading="Token wallets">
        <div style="overflow-x:auto">
            <table style="width:100%;text-align:left">
                <thead><tr><th>Token</th><th>Available</th><th>Reserved</th><th>Total</th></tr></thead>
                <tbody>
                    @forelse($wallets as $wallet)
                        <tr><td class="py-3">{{ $wallet->tokenType?->name }}</td><td>{{ number_format($wallet->balance) }}</td><td>{{ number_format($wallet->locked_balance) }}</td><td>{{ number_format($wallet->total_balance) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-gray-500">No token activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4 flex flex-wrap gap-4">
            <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\TokenWalletResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]) }}">Manage wallets</a>
            <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\TokenPurchaseResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]) }}">Purchase history</a>
        </div>
    </x-filament::section>
    <x-filament::section heading="Performance earnings">
        <p>Current payout schedule: <strong>{{ ucfirst($frequency) }}</strong></p>
        @if($next)<p class="text-sm text-gray-500">Scheduled change: {{ ucfirst($next->frequency) }} from {{ $next->effective_from->format('d M Y') }}.</p>@endif
        <div class="mt-4">
            @forelse($balances as $balance)
                <p>Ledger balance: <strong>{{ $balance->currency }} {{ number_format($balance->total / 100, 2) }}</strong></p>
            @empty
                <p class="text-gray-500">No earnings ledger entries yet.</p>
            @endforelse
            @foreach($paid as $payment)
                <p>Paid to date: <strong>{{ $payment->currency }} {{ number_format($payment->total / 100, 2) }}</strong></p>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-4">
            <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\RewardPeriodResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]) }}">Periods & multipliers</a>
            <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\RewardPayoutResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]) }}">Payout history</a>
            <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\RewardLedgerEntryResource::getUrl('index', ['tableFilters' => ['user_id' => ['value' => $user->id]]]) }}">Earnings ledger</a>
        </div>
        <div class="mt-4" style="overflow-x:auto">
            <table style="width:100%;text-align:left">
                <thead><tr><th>Recent period</th><th>Schedule</th><th>Status</th><th>Calculated amount</th></tr></thead>
                <tbody>
                    @foreach($periods as $period)
                        <tr><td class="py-3">{{ $period->starts_at->format('d M') }} – {{ $period->ends_at->format('d M Y') }}</td><td>{{ ucfirst($period->frequency) }}</td><td>{{ str($period->status)->replace('_',' ')->title() }}</td><td>{{ $period->currency }} {{ number_format($period->calculated_amount_minor / 100, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    @if($user->legacyWallet || $user->subscriptions()->exists())
        <x-filament::section heading="Legacy billing records" description="Historical records; these do not determine current performance payouts.">
            @if($user->legacyWallet)<p>Legacy credits: {{ $user->legacyWallet->balance }} available · {{ $user->legacyWallet->locked_balance }} reserved.</p>@endif
            @foreach($user->subscriptions()->with('plan')->latest('starts_at')->limit(5)->get() as $subscription)
                <p>{{ $subscription->plan?->name }} · {{ $subscription->status }} · {{ $subscription->ends_at?->format('d M Y') }}</p>
            @endforeach
        </x-filament::section>
    @endif
</div>
