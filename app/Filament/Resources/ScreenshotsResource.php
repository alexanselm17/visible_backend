<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Models\Screenshots;
use App\Support\AdminWorkflow;
use Filament\Forms\Components as F;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters as T;
use Filament\Tables\Table;

class ScreenshotsResource extends OperationsResource
{
    protected static ?string $model = Screenshots::class;

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Posting screenshots';

    protected static ?int $navigationSort = 4;

    protected static string $area = 'campaigns';

    protected static array $exportColumns = ['id', 'advert.name', 'user.fullname', 'number', 'views', 'timestamp', 'created_at'];

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('advert.campaign', 'user'))->columns([
            C\ImageColumn::make('screenshot')->disk('admin_media')->label('Evidence')->square()->size(56),
            C\TextColumn::make('advert.name')->label('Advert')->searchable()->description(fn ($record) => $record->advert?->campaign?->name),
            C\TextColumn::make('user.fullname')->label('Member')->searchable(),
            C\TextColumn::make('number')->label('Stage')->badge()->formatStateUsing(fn ($state) => $state == 2 ? '2 · Complete' : '1 · Started'),
            C\TextColumn::make('views')->numeric()->sortable(),
            C\TextColumn::make('timestamp')->label('Reported time'),
            C\TextColumn::make('source')->state(fn ($record) => str_starts_with($record->screenshot, 'screenshots/manual_') ? 'Staff entry' : 'Verified upload')->badge(),
            C\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->filters([
            T\SelectFilter::make('advert_id')->relationship('advert', 'name')->searchable()->label('Advert'),
            T\SelectFilter::make('processed_by')->relationship('user', 'fullname')->searchable()->label('Member'),
            T\SelectFilter::make('number')->options([1 => 'First screenshot', 2 => 'Second / complete']),
            T\Filter::make('manual')->label('Staff entries')->query(fn ($query) => $query->where('screenshot', 'like', 'screenshots/manual_%')),
        ])->headerActions([
            A\Action::make('manual')->label('Record verified values')->icon('heroicon-o-pencil-square')
                ->modalDescription('For a WhatsApp screenshot already checked by staff. The 18-hour gap, increasing views, capacity and completion rules still apply.')
                ->form([
                    Fields::advert()->required(),
                    Fields::user()->required(), F\TextInput::make('views')->integer()->minValue(0)->required(),
                    F\TextInput::make('timestamp')->maxLength(255)->helperText('Optional time shown on the screenshot.'),
                ])->requiresConfirmation()
                ->action(fn (array $data) => AdminWorkflow::run(\App\Http\Controllers\ProductController::class, 'submitManualScreenshot', $data, ['advert_id' => $data['advert_id']])),
        ])->actions([
            A\Action::make('preview')->label('View evidence')->icon('heroicon-o-eye')->modalSubmitAction(false)->modalWidth('3xl')
                ->modalContent(fn ($record) => view('filament.components.image-preview', ['url' => asset('storage/'.$record->screenshot)])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ScreenshotsResource\Pages\ListScreenshots::route('/')];
    }
}
