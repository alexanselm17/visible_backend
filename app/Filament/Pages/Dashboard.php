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


  // public function filtersForm(Form $form): Form
  // {
  //   $user = FacadesAuth::user();

  //   // Retrieve petrol stations associated with the user's company
  //   $petrolStations = PetrolStation::where('company_id', $user->company_id)
  //     ->pluck('name', 'id');

  //   return $form->schema([
  //     Section::make()->schema([
  //       // Petrol Station dropdown limited to the user's company
  //       Select::make('petrol_station')
  //         ->label('Petrol Station')
  //         ->options($petrolStations)
  //         ->placeholder('Select a Petrol Station'),

  //       // Date pickers for start and end date
  //       DatePicker::make('startDate'),
  //       DatePicker::make('endDate'),
  //     ])->columns(3),
  //   ]);
  // }

  public function getWidgets(): array
  {
    return [
      // StatsOverview::class,
      // SalesChart::class,
      // FuelChart::class,
    ];
  }
}
