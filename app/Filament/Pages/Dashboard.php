<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BarChartWidget;
use App\Filament\Widgets\FuelChart;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\StatsOverview;
use App\Models\PetrolStation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Support\Facades\Auth as FacadesAuth;

class Dashboard extends BaseDashboard
{
  use HasFiltersForm;




  public function getWidgets(): array
  {
     return [
      \App\Filament\Widgets\CampaignCardsWidget::class,
      \App\Filament\Widgets\RecentActivityWidget::class,
      \App\Filament\Widgets\ViewsChart::class,
      \App\Filament\Widgets\ScreenshotsSubmissionChart::class,
      \App\Filament\Widgets\RevenueChart::class,
      \App\Filament\Widgets\UserEngagementChart::class,
      \App\Filament\Widgets\TopPerformingAdvertsChart::class,
      
    ];
  }
}
