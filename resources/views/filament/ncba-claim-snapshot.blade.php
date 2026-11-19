<div class="space-y-2 text-sm">
    <p><strong>Receipt:</strong> {{ $claim->receipt_number }} · <strong>Attempt:</strong> {{ $claim->sequence }}</p>
    <p><strong>Verified phone at submission:</strong> {{ $claim->phone_verified_at_snapshot?->format('Y-m-d H:i:s') ?? 'Not verified' }}</p>
    <p><strong>Owner phone snapshot:</strong> {{ $claim->owner_phone_snapshot ?? 'Not available' }}</p>
    <dl class="space-y-1">
        @foreach ($claim->purchase_snapshot as $key => $value)
            <div><dt class="inline font-medium">{{ str_replace('_', ' ', ucfirst($key)) }}:</dt> <dd class="inline">{{ $value }}</dd></div>
        @endforeach
    </dl>
</div>
