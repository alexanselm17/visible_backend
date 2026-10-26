<div class="space-y-5">
    <p class="text-sm text-gray-500">{{ $period->status === 'open' ? 'Live estimate. Recalculate to include recent verified activity.' : 'Saved period calculation. Payment does not erase this history.' }}</p>
    @foreach($period->metrics as $metric)
        <x-filament::section :heading="$metric->metric?->name ?? 'Metric'">
            <p>Measured: {{ (float)$metric->measured_value }} · Multiplier: × {{ $metric->multiplier_basis_points / 10000 }}</p>
            <dl class="mt-3 space-y-2 text-sm text-gray-500">
                @foreach($metric->evidence ?? [] as $key => $value)
                    <div><dt class="font-medium">{{ str($key)->replace('_', ' ')->title() }}</dt><dd>{{ is_array($value) ? json_encode($value) : $value }}</dd></div>
                @endforeach
            </dl>
        </x-filament::section>
    @endforeach
    <p class="font-semibold">{{ $period->currency }} {{ number_format($period->maximum_amount_minor / 100, 2) }} × {{ $period->combined_multiplier_basis_points / 10000 }} = {{ $period->currency }} {{ number_format($period->calculated_amount_minor / 100, 2) }}</p>
</div>

