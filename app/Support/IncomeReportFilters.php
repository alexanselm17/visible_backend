<?php

namespace App\Support;

use App\Models\TokenType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IncomeReportFilters
{
    public function __construct(public readonly array $values) {}

    public static function defaults(): array
    {
        $today = CarbonImmutable::now('Africa/Nairobi');

        return [
            'preset' => 'this_month', 'from_date' => $today->startOfMonth()->toDateString(),
            'to_date' => $today->toDateString(), 'currency' => 'KES', 'token_type' => 'all',
            'comparison_year' => $today->year, 'compare_year' => $today->year - 1, 'comparison_scope' => 'ytd',
        ];
    }

    public static function make(?array $input): self
    {
        $data = array_replace(self::defaults(), array_intersect_key($input ?? [], self::defaults()));
        $today = CarbonImmutable::now('Africa/Nairobi');
        $data = Validator::make($data, [
            'preset' => ['required', Rule::in(array_keys(self::presets()))],
            'from_date' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:2000-01-01'],
            'to_date' => ['required', 'string', 'date_format:Y-m-d', 'after_or_equal:from_date', 'before_or_equal:'.$today->toDateString()],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/D'],
            'token_type' => ['required', Rule::in(['all', ...TokenType::codes()])],
            'comparison_year' => ['required', 'integer', 'min:2000', 'max:'.$today->year],
            'compare_year' => ['required', 'integer', 'min:2000', 'max:'.$today->year, 'different:comparison_year'],
            'comparison_scope' => ['required', Rule::in(['ytd', 'calendar'])],
        ])->validate();
        if (CarbonImmutable::parse($data['from_date'])->diffInDays(CarbonImmutable::parse($data['to_date'])) > 3660) {
            throw ValidationException::withMessages(['from_date' => 'Choose a date range of ten years or less.']);
        }
        $data['comparison_year'] = (int) $data['comparison_year'];
        $data['compare_year'] = (int) $data['compare_year'];

        return new self($data);
    }

    public static function presets(): array
    {
        return ['today' => 'Today', 'yesterday' => 'Yesterday', 'last_7' => 'Last 7 days',
            'last_30' => 'Last 30 days', 'this_month' => 'This month', 'last_month' => 'Last month',
            'this_year' => 'This year', 'last_year' => 'Last year', 'custom' => 'Custom date range'];
    }

    public static function presetDates(string $preset): array
    {
        $today = CarbonImmutable::now('Africa/Nairobi')->startOfDay();
        [$from, $to] = match ($preset) {
            'today' => [$today, $today], 'yesterday' => [$today->subDay(), $today->subDay()],
            'last_7' => [$today->subDays(6), $today], 'last_30' => [$today->subDays(29), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->startOfMonth()->subDay()],
            'this_year' => [$today->startOfYear(), $today],
            'last_year' => [$today->subYear()->startOfYear(), $today->startOfYear()->subDay()],
            default => [$today->startOfMonth(), $today],
        };

        return ['from_date' => $from->toDateString(), 'to_date' => $to->toDateString()];
    }

    /** Half-open boundaries: inclusive local dates without losing fractional seconds. */
    public function bounds(bool $previous = false): array
    {
        $start = CarbonImmutable::parse($this->values['from_date'], 'Africa/Nairobi')->startOfDay();
        $end = CarbonImmutable::parse($this->values['to_date'], 'Africa/Nairobi')->addDay()->startOfDay();
        if ($previous) {
            $days = (int) $start->diffInDays($end);
            [$start, $end] = [$start->subDays($days), $start];
        }

        return [$start->timezone(config('app.timezone')), $end->timezone(config('app.timezone'))];
    }

    public function comparisonBounds(int $year): array
    {
        $today = CarbonImmutable::now('Africa/Nairobi')->startOfDay();
        $start = $today->setDate($year, 1, 1);
        $end = $start->addYear();
        if ($this->values['comparison_scope'] === 'ytd') {
            $cutoff = $this->comparisonCutoff();
            $month = $start->setMonth($cutoff->month);
            $end = $month->setDay(min($cutoff->day, $month->daysInMonth))->addDay();
        }
        $end = $end->min($today->addDay());

        return [$start->timezone(config('app.timezone')), $end->timezone(config('app.timezone'))];
    }

    public function comparisonCutoff(): CarbonImmutable
    {
        $cutoff = CarbonImmutable::parse($this->values['to_date'], 'Africa/Nairobi');
        $today = CarbonImmutable::now('Africa/Nairobi')->startOfDay();
        // When either comparison year is still underway, apply its elapsed cutoff
        // to BOTH years, even if the report period ends in a prior December.
        if (in_array($today->year, [$this->values['comparison_year'], $this->values['compare_year']], true)
            && $cutoff->format('m-d') > $today->format('m-d')) {
            return $today;
        }

        return $cutoff;
    }

    public function currency(): string
    {
        return $this->values['currency'];
    }

    public function token(): string
    {
        return $this->values['token_type'];
    }

    public function label(): string
    {
        return $this->values['from_date'].' → '.$this->values['to_date'].' · '.$this->currency().' · EAT';
    }
}
