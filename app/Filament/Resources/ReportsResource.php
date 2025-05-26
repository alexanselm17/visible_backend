<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\PetrolStation;
use App\Models\Shift;
use App\Models\Customer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Forms\Form;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use App\Models\User;

class ReportResource extends Resource
{
  protected static ?string $model = PetrolStation::class;
  protected static ?string $navigationIcon = 'heroicon-o-document-text';
  protected static ?string $navigationGroup = 'Report';
  protected static ?string $navigationLabel = 'Report';

  public static function form(Form $form): Form
  {
    return $form
      ->schema([
        //
      ]);
  }



  public static function getPages(): array
  {
    return [
      'sales' => Pages\GenerateSalesReport::route('/sales'),
      'personal-sales' => Pages\GeneratePersonalSalesReport::route('/personal-sales'),
      'customer' => Pages\GenerateCustomerReport::route('/customer'),
      'stock' => Pages\GenerateStockReport::route('/stock'),
      'periodic-salesman' => Pages\GeneratePeriodicSalesmanReport::route('/periodic-salesman'),
    ];
  }
}

namespace App\Filament\Resources\ReportResource\Pages;

use App\Filament\Resources\ReportResource;
use Filament\Resources\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use App\Models\PetrolStation;
use App\Models\Shift;
use App\Models\User;
use App\Models\Customer;
use App\Models\Customers;

class GenerateSalesReport extends Page implements HasForms
{
  protected static string $resource = ReportResource::class;
  protected static string $view = 'filament.resources.report-resource.pages.generate-sales-report';
  public ?array $data = [];
  public $report = null;

  public function mount(): void
  {
    $this->form->fill();
  }

  public function form(Form $form): Form
  {
    return $form
      ->schema([
        Select::make('petrol_station_id')
          ->label('Petrol Station')
          ->options(PetrolStation::pluck('name', 'id'))
          ->required()
          ->reactive()
          ->afterStateUpdated(fn($state) => $this->data['shift_id'] = null),

        Select::make('shift_id')
          ->label('Shift')
          ->options(function (callable $get) {
            $petrolId = $get('petrol_station_id');
            if (!$petrolId) return [];
            return Shift::where('petrol_id', $petrolId)
              ->pluck('description', 'id');
          })
          ->required()
          ->disabled(fn($get) => !$get('petrol_station_id')),
      ])
      ->statePath('data');
  }

  public function generate()
  {
    $data = $this->form->getState();
    $this->report = app(\App\Http\Controllers\SalesController::class)
      ->generateSalesReport(request(), $data['petrol_station_id'], $data['shift_id']);
  }
}

class GeneratePersonalSalesReport extends Page implements HasForms
{
  protected static string $resource = ReportResource::class;
  protected static string $view = 'filament.resources.report-resource.pages.generate-personal-sales-report';
  public ?array $data = [];
  public $report = null;

  public function mount(): void
  {
    $this->form->fill();
  }

  public function form(Form $form): Form
  {
    return $form
      ->schema([
        Select::make('petrol_station_id')
          ->label('Petrol Station')
          ->options(PetrolStation::pluck('name', 'id'))
          ->required()
          ->reactive(),

        Select::make('user_id')
          ->label('User')
          ->options(User::pluck('fullname', 'id'))
          ->required(),

        Select::make('shift_id')
          ->label('Shift')
          ->options(function (callable $get) {
            $petrolId = $get('petrol_station_id');
            if (!$petrolId) return [];
            return Shift::where('petrol_id', $petrolId)
              ->pluck('description', 'id');
          })
          ->required(),
      ])
      ->statePath('data');
  }

  public function generate()
  {
    $data = $this->form->getState();
    $this->report = app(\App\Http\Controllers\SalesController::class)
      ->generatePersonalSalesReport(new \App\Http\Requests\PersonalSalesReport($data));
  }
}

class GenerateCustomerReport extends Page implements HasForms
{
  protected static string $resource = ReportResource::class;
  protected static string $view = 'filament.resources.report-resource.pages.generate-customer-report';
  public ?array $data = [];
  public $report = null;

  public function mount(): void
  {
    $this->form->fill();
  }

  public function form(Form $form): Form
  {
    return $form
      ->schema([
        Select::make('petrol_id')
          ->label('Petrol Station')
          ->options(PetrolStation::pluck('name', 'id'))
          ->required()
          ->reactive(),

        Select::make('customer_id')
          ->label('Customer')
          ->options(function (callable $get) {
            $petrolId = $get('petrol_id');
            if (!$petrolId) return [];
            return Customers::where('petrol_id', $petrolId)
              ->pluck('name', 'id');
          })
          ->required(),

        DatePicker::make('from')
          ->required(),

        DatePicker::make('to')
          ->required(),
      ])
      ->statePath('data');
  }

  public function generate()
  {
    $data = $this->form->getState();
    $this->report = app(\App\Http\Controllers\SalesController::class)
      ->generateCustomerReport(new \App\Http\Requests\CustomerReport($data));
  }
}

class GenerateStockReport extends Page implements HasForms
{
  protected static string $resource = ReportResource::class;
  protected static string $view = 'filament.resources.report-resource.pages.generate-stock-report';
  public ?array $data = [];
  public $report = null;

  public function mount(): void
  {
    $this->form->fill();
  }

  public function form(Form $form): Form
  {
    return $form
      ->schema([
        Select::make('petrol_station_id')
          ->label('Petrol Station')
          ->options(PetrolStation::pluck('name', 'id'))
          ->required()
          ->reactive(),

        Select::make('drum_id')
          ->label('Drum')
          ->options(function (callable $get) {
            $petrolId = $get('petrol_station_id');
            if (!$petrolId) return [];
            return \App\Models\Drum::where('petrol_id', $petrolId)
              ->pluck('name', 'id');
          })
          ->reactive(),

        Select::make('station_id')
          ->label('Station')
          ->options(function (callable $get) {
            $petrolId = $get('petrol_station_id');
            if (!$petrolId) return [];
            return \App\Models\Stations::where('petrol_id', $petrolId)
              ->pluck('name', 'id');
          })
          ->reactive(),
      ])
      ->statePath('data');
  }

  public function generate()
  {
    $data = $this->form->getState();
    $this->report = app(\App\Http\Controllers\SalesController::class)
      ->generateStockReport(new \App\Http\Requests\StockReport($data), $data['petrol_station_id']);
  }
}

class GeneratePeriodicSalesmanReport extends Page implements HasForms
{
  protected static string $resource = ReportResource::class;
  protected static string $view = 'filament.resources.report-resource.pages.generate-periodic-salesman-report';
  public ?array $data = [];
  public $report = null;

  public function mount(): void
  {
    $this->form->fill();
  }

  public function form(Form $form): Form
  {
    return $form
      ->schema([
        Select::make('petrol_id')
          ->label('Petrol Station')
          ->options(PetrolStation::pluck('name', 'id'))
          ->required()
          ->reactive(),

        Select::make('salesman')
          ->label('Salesman')
          ->options(User::pluck('fullname', 'id'))
          ->required(),

        DatePicker::make('from')
          ->required(),

        DatePicker::make('to')
          ->required(),
      ])
      ->statePath('data');
  }

  public function generate()
  {
    $data = $this->form->getState();
    $this->report = app(\App\Http\Controllers\SalesController::class)
      ->periodicSalesmanReport(new \Illuminate\Http\Request($data));
  }
}
