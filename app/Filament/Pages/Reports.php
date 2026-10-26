<?php

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Pages\Page;

class Reports extends Page
{
    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Reports & exports';

    protected static string $view = 'filament.pages.reports';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAccess::allows('reports');
    }

    public function mount(): void
    {
        $this->form->fill(['report' => 'campaign_report', 'from_date' => now()->subMonth()->toDateString(), 'to_date' => now()->toDateString()]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            F\Select::make('report')->label('Report')->required()->live()->options([
                'campaign_report' => 'Campaign overview',
                'timely_campaign_report' => 'Campaign activity between dates',
                'user.report' => 'Individual member activity between dates',
            ]),
            F\Select::make('campaign_id')->label('Campaign')->options(fn () => Campaign::pluck('name', 'id'))->searchable()
                ->required(fn (\Filament\Forms\Get $get) => $get('report') !== 'user.report')->visible(fn (\Filament\Forms\Get $get) => $get('report') !== 'user.report'),
            \App\Filament\Support\Fields::user()->required(fn (\Filament\Forms\Get $get) => $get('report') === 'user.report')->visible(fn (\Filament\Forms\Get $get) => $get('report') === 'user.report'),
            F\DatePicker::make('from_date')->required(), F\DatePicker::make('to_date')->required()->afterOrEqual('from_date'),
        ])->columns(2)->statePath('data');
    }

    public function download()
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $route = $data['report'];
        unset($data['report']);

        return redirect()->route($route, $data);
    }
}
