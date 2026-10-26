<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CampaignResource\Pages;
use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\Campaign;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Infolists\Components as I;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;

class CampaignResource extends OperationsResource
{
    protected static ?string $model = Campaign::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?int $navigationSort = 2;

    protected static string $area = 'campaigns';

    protected static bool $editable = true;

    protected static array $exportColumns = ['id', 'name', 'owner.fullname', 'created_at'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Campaign')->description('Group an owner’s adverts and submissions in one place.')->schema([
                F\TextInput::make('name')->required()->maxLength(255),
                Fields::user('owner_id', 'Campaign owner')->required(),
            ])->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            I\Section::make('Campaign')->columns(3)->schema([
                I\TextEntry::make('name'), I\TextEntry::make('owner.fullname')->label('Owner'),
                I\TextEntry::make('created_at')->dateTime(),
                I\TextEntry::make('adverts_count')->state(fn ($record) => $record->adverts()->count())->label('Adverts'),
                I\TextEntry::make('live_adverts')->state(fn ($record) => $record->adverts()->where('valid_until', '>=', now())->count())->label('Live adverts'),
                I\TextEntry::make('completed_tasks')->state(fn ($record) => \App\Models\Screenshots::whereHas('advert', fn ($query) => $query->where('campaign_id', $record->id))->where('number', 2)->count()),
                I\TextEntry::make('total_capacity')->state(fn ($record) => $record->adverts()->sum('capacity'))->label('Total participant capacity'),
                I\TextEntry::make('screenshots')->state(fn ($record) => \App\Models\Screenshots::whereHas('advert', fn ($query) => $query->where('campaign_id', $record->id))->count()),
                I\TextEntry::make('verified_views')->state(fn ($record) => \App\Models\Screenshots::whereHas('advert', fn ($query) => $query->where('campaign_id', $record->id))->where('number', 2)->sum('views')),
                I\TextEntry::make('participants')->state(fn ($record) => \App\Models\Screenshots::whereHas('advert', fn ($query) => $query->where('campaign_id', $record->id))->distinct()->count('processed_by')),
                I\TextEntry::make('days_running')->state(fn ($record) => (int) $record->created_at->diffInDays(now())),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('owner')->withCount([
            'adverts', 'adverts as live_adverts_count' => fn ($query) => $query->where('valid_until', '>=', now()),
        ]))->columns([
            C\TextColumn::make('name')->searchable()->sortable()->weight('semibold'),
            C\TextColumn::make('owner.fullname')->label('Owner')->searchable()->placeholder('No owner assigned'),
            C\TextColumn::make('adverts_count')->label('Adverts')->numeric()->sortable(),
            C\TextColumn::make('live_adverts_count')->label('Live')->numeric()->sortable(),
            C\TextColumn::make('created_at')->date()->sortable(),
        ])->filters([
            \Filament\Tables\Filters\SelectFilter::make('owner_id')->relationship('owner', 'fullname')->label('Owner')->searchable(),
        ])->actions([
            A\ViewAction::make(), A\EditAction::make(),
            A\Action::make('report')->label('Campaign report')->icon('heroicon-o-document-chart-bar')
                ->url(fn ($record) => route('campaign_report', ['campaign_id' => $record->id]))->openUrlInNewTab(),
            A\Action::make('adverts')->url(fn ($record) => AdvertImagesResource::getUrl('index', ['tableFilters' => ['campaign_id' => ['value' => $record->id]]])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListCampaigns::route('/'), 'create' => Pages\CreateCampaign::route('/create'),
            'view' => Pages\ViewCampaign::route('/{record}'), 'edit' => Pages\EditCampaign::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [CampaignResource\RelationManagers\AdvertsRelationManager::class];
    }
}
