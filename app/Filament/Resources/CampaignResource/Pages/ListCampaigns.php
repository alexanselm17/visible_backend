<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('timelyCampaignReport')
                ->label('Timely Campaign Report')
                ->icon('heroicon-o-clock')
                ->outlined(false)
                ->extraAttributes([
                    'class' => 'text-white font-semibold px-4 py-2 rounded-lg shadow-sm transition-colors duration-200',
                    'style' => 'background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); border: none; color: white !important;',
                ])
                ->form([
                    Forms\Components\DatePicker::make('from_date')
                        ->label('From Date')
                        ->required()
                        ->default(now()->subMonth()),

                    Forms\Components\DatePicker::make('to_date')
                        ->label('To Date')
                        ->required()
                        ->default(now()),
                ])
                ->action(function (array $data) {
                    return redirect()->route('timely_campaign_report', [
                        'from_date' => $data['from_date'],
                        'to_date' => $data['to_date'],
                    ]);
                })
                ->modalHeading('Generate Timely Campaign Report')
                ->modalSubmitActionLabel('Generate Report'),
        ];
    }
}
