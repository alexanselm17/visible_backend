<div class="space-y-6">
    <p class="text-sm text-gray-500">{{ $plan->name }} · v{{ $plan->version }} · {{ $plan->calculation_method === 'multiply' ? 'Monthly maximum × every multiplier' : 'Weighted average of multipliers' }}</p>
    @foreach($plan->planMetrics as $metric)
        <x-filament::section :heading="$metric->metric->name">
            <p class="text-sm text-gray-500">Measure: {{ $metric->metric->evaluator_key }} · Weight: {{ $metric->weight_basis_points / 10000 }}</p>
            <table style="width:100%;text-align:left;margin-top:12px">
                <thead><tr><th>From</th><th>Through</th><th>Multiplier</th></tr></thead>
                <tbody>
                    @foreach($metric->tiers as $tier)
                        <tr><td class="py-2">{{ (float)$tier->minimum_value }}</td><td>{{ $tier->maximum_value === null ? 'No limit' : (float)$tier->maximum_value }}</td><td>× {{ $tier->multiplier_basis_points / 10000 }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endforeach
</div>

