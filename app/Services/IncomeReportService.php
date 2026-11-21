<?php

namespace App\Services;

use App\Models\NcbaPayment;
use App\Models\NcbaReceiptClaim;
use App\Models\TokenPurchase;
use App\Models\TokenType;
use App\Support\AdminAccess;
use App\Support\IncomeReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IncomeReportService
{
    private function authorize(): void
    {
        abort_unless(AdminAccess::allows('finance'), 403);
    }

    public function currencies(): array
    {
        $this->authorize();

        return collect(['KES'])->merge(TokenPurchase::distinct()->pluck('currency'))
            ->merge(NcbaPayment::distinct()->pluck('currency'))->filter(fn ($value) => is_string($value) && preg_match('/^[A-Z]{3}$/D', $value))
            ->unique()->sort()->mapWithKeys(fn ($currency) => [$currency => $currency])->all();
    }

    public function purchases(IncomeReportFilters $filters): Builder
    {
        $this->authorize();

        return TokenPurchase::where('currency', $filters->currency())
            ->when($filters->token() !== 'all', fn ($q) => $q->whereHas('tokenType', fn ($type) => $type->where('code', $filters->token())));
    }

    private function between(Builder $query, string $column, array $bounds): Builder
    {
        return $query->where($column, '>=', $bounds[0])->where($column, '<', $bounds[1]);
    }

    public function approved(IncomeReportFilters $filters, ?array $bounds = null): Builder
    {
        return $this->between($this->purchases($filters)->where('status', TokenPurchase::PAID), 'paid_at', $bounds ?? $filters->bounds());
    }

    public function orders(IncomeReportFilters $filters): Builder
    {
        return $this->between($this->purchases($filters), 'created_at', $filters->bounds());
    }

    public function payments(IncomeReportFilters $filters): Builder
    {
        $this->authorize();

        return $this->between(NcbaPayment::where('currency', $filters->currency())
            ->when($filters->token() !== 'all', fn ($query) => $query->whereHas('purchase.tokenType', fn ($type) => $type->where('code', $filters->token()))),
            'transacted_at', $filters->bounds());
    }

    public function purchaseTable(IncomeReportFilters $filters): Builder
    {
        $bounds = $filters->bounds();

        return $this->purchases($filters)->where(function ($query) use ($bounds) {
            $query->where(fn ($paid) => $this->between($paid->where('status', TokenPurchase::PAID), 'paid_at', $bounds))
                ->orWhere(fn ($other) => $this->between($other->where('status', '!=', TokenPurchase::PAID), 'created_at', $bounds));
        });
    }

    private function moneySql(): string
    {
        $cast = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'SIGNED';

        return "CAST(ROUND(total_amount * 100, 0) AS {$cast})";
    }

    private function total(Builder $query): array
    {
        $row = $query->selectRaw('COUNT(*) AS report_count, COALESCE(SUM('.$this->moneySql().'), 0) AS report_minor, COALESCE(SUM(quantity), 0) AS report_tokens')->first();

        return ['count' => (int) $row->report_count, 'minor' => (int) $row->report_minor, 'tokens' => (int) $row->report_tokens];
    }

    public function summary(IncomeReportFilters $filters): array
    {
        $approved = $this->total($this->approved($filters));
        $previous = $this->total($this->approved($filters, $filters->bounds(previous: true)));
        $orders = $this->orderStatuses($filters);
        $bank = $this->bankStatuses($filters);
        $claims = $this->between(NcbaReceiptClaim::whereHas('purchase', fn ($q) => $q->where('currency', $filters->currency())
            ->when($filters->token() !== 'all', fn ($q) => $q->whereHas('tokenType', fn ($type) => $type->where('code', $filters->token())))), 'submitted_at', $filters->bounds())
            ->whereIn('status', [NcbaReceiptClaim::WAITING_BANK, NcbaReceiptClaim::REVIEW])->whereNull('ncba_payment_id')->count();
        $repeat = DB::query()->fromSub($this->approved($filters)->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1'), 'repeat_buyers')->count();

        return ['approved' => $approved, 'previous' => $previous, 'orders' => $orders, 'bank' => $bank,
            'bank_total_minor' => array_sum(array_column($bank, 'minor')), 'bank_count' => array_sum(array_column($bank, 'count')),
            'buyers' => $this->approved($filters)->distinct()->count('user_id'), 'repeat_buyers' => $repeat,
            'waiting_claims' => $claims,
            'missing_paid_dates' => $this->orders($filters)->where('status', TokenPurchase::PAID)->whereNull('paid_at')->count(),
            'average_minor' => $approved['count'] ? intdiv($approved['minor'] + intdiv($approved['count'], 2), $approved['count']) : 0,
            'growth_percent' => $previous['minor'] > 0 ? round(($approved['minor'] - $previous['minor']) / $previous['minor'] * 100, 1) : null];
    }

    public function orderStatuses(IncomeReportFilters $filters): array
    {
        return $this->orders($filters)->selectRaw('status, COUNT(*) AS report_count, COALESCE(SUM('.$this->moneySql().'), 0) AS report_minor')
            ->groupBy('status')->get()->keyBy('status')->map(fn ($row) => ['count' => (int) $row->report_count, 'minor' => (int) $row->report_minor])->all();
    }

    public function bankStatuses(IncomeReportFilters $filters): array
    {
        return $this->payments($filters)->selectRaw('status, COUNT(*) AS report_count, COALESCE(SUM(amount_minor), 0) AS report_minor')->groupBy('status')->get()
            ->keyBy('status')->map(fn ($row) => ['count' => (int) $row->report_count, 'minor' => (int) $row->report_minor])->all();
    }

    private function bucketSql(string $column, string $bucket): string
    {
        $formats = ['day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y'];
        $format = $formats[$bucket];
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('{$format}', {$column})";
        }

        return "DATE_FORMAT({$column}, '{$format}')";
    }

    private function grouped(Builder $query, string $bucket): array
    {
        $expression = $this->bucketSql('paid_at', $bucket);

        return $query->selectRaw("{$expression} AS report_bucket, COUNT(*) AS report_count, COALESCE(SUM(".$this->moneySql().'), 0) AS report_minor')
            ->groupByRaw($expression)->orderBy('report_bucket')->get()->keyBy('report_bucket')
            ->map(fn ($row) => ['minor' => (int) $row->report_minor, 'count' => (int) $row->report_count])->all();
    }

    public function trend(IncomeReportFilters $filters): array
    {
        [$start, $end] = $filters->bounds();
        $days = $start->diffInDays($end);
        $bucket = $days <= 62 ? 'day' : ($days <= 1096 ? 'month' : 'year');
        $rows = $this->grouped($this->approved($filters), $bucket);
        $cursor = match ($bucket) {
            'month' => $start->startOfMonth(), 'year' => $start->startOfYear(), default => $start
        };
        $format = match ($bucket) {
            'month' => 'Y-m', 'year' => 'Y', default => 'Y-m-d'
        };
        $label = match ($bucket) {
            'month' => 'M Y', 'year' => 'Y', default => 'd M'
        };
        $result = ['bucket' => $bucket, 'labels' => [], 'minor' => [], 'counts' => []];
        while ($cursor->lt($end)) {
            $row = $rows[$cursor->format($format)] ?? ['minor' => 0, 'count' => 0];
            $result['labels'][] = $cursor->format($label);
            $result['minor'][] = $row['minor'];
            $result['counts'][] = $row['count'];
            $cursor = match ($bucket) {
                'month' => $cursor->addMonth(), 'year' => $cursor->addYear(), default => $cursor->addDay()
            };
        }

        return $result;
    }

    public function monthly(IncomeReportFilters $filters): array
    {
        $datasets = [];
        foreach ([$filters->values['comparison_year'], $filters->values['compare_year']] as $year) {
            $bounds = $filters->comparisonBounds($year);
            $rows = $this->grouped($this->approved($filters, $bounds), 'month');
            $values = [];
            for ($month = 1; $month <= 12; $month++) {
                $date = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'));
                $values[] = $date->gte($bounds[1]) ? null : ($rows[$date->format('Y-m')]['minor'] ?? 0);
            }
            $datasets[$year] = $values;
        }

        return ['labels' => array_map(fn ($month) => CarbonImmutable::create(2000, $month, 1)->format('M'), range(1, 12)), 'years' => $datasets];
    }

    public function yearly(IncomeReportFilters $filters): array
    {
        $endYear = $filters->values['comparison_year'];
        $years = range(max(2000, $endYear - 4), $endYear);
        $query = $this->purchases($filters)->where('status', TokenPurchase::PAID)->where(function ($query) use ($filters, $years) {
            foreach ($years as $year) {
                $query->orWhere(fn ($yearQuery) => $this->between($yearQuery, 'paid_at', $filters->comparisonBounds($year)));
            }
        });
        $rows = $this->grouped($query, 'year');

        return ['labels' => array_map('strval', $years), 'minor' => array_map(fn ($year) => $rows[$year]['minor'] ?? 0, $years)];
    }

    public function byToken(IncomeReportFilters $filters): array
    {
        $rows = $this->approved($filters)->selectRaw('token_type_id, COUNT(*) AS report_count, COALESCE(SUM('.$this->moneySql().'), 0) AS report_minor')
            ->groupBy('token_type_id')->get()->keyBy('token_type_id');
        $types = TokenType::when($filters->token() !== 'all', fn ($q) => $q->where('code', $filters->token()))->orderBy('sort_order')->get(['id', 'code']);

        return ['labels' => $types->pluck('code')->all(), 'minor' => $types->map(fn ($type) => (int) ($rows->get($type->id)?->report_minor ?? 0))->all()];
    }

    public static function money(int $minor, string $currency): string
    {
        return $currency.' '.number_format(intdiv($minor, 100)).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
