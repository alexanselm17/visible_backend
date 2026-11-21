<?php

namespace App\Filament\Widgets\Income;

use App\Services\IncomeReportService;
use App\Support\AdminAccess;
use App\Support\IncomeReportFilters;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait UsesIncomeFilters
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return AdminAccess::allows('finance');
    }

    public function bootUsesIncomeFilters(): void
    {
        abort_unless(static::canView(), 403);
    }

    protected function incomeFilters(): IncomeReportFilters
    {
        abort_unless(static::canView(), 403);

        return IncomeReportFilters::make($this->filters);
    }

    protected function reports(): IncomeReportService
    {
        return app(IncomeReportService::class);
    }
}
