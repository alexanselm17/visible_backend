<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvertImagesResource\Pages;
use App\Filament\Support\OperationsResource;
use App\Models\AdvertImages;
use App\Models\TokenType;
use App\Support\AdminWorkflow;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters as T;
use Filament\Tables\Table;

class AdvertImagesResource extends OperationsResource
{
    protected static ?string $model = AdvertImages::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Published adverts';

    protected static ?int $navigationSort = 3;

    protected static string $area = 'campaigns';

    protected static bool $editable = true;

    protected static array $exportColumns = ['id', 'name', 'campaign.name', 'type', 'capacity', 'valid_until', 'submission_id', 'created_at'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Advert details')->description('Owner-funded adverts should be published from Submission workflow, where token reservations are settled. This form also supports direct staff campaigns.')->columns(2)->schema([
                F\TextInput::make('name')->required()->maxLength(255),
                F\Select::make('campaign_id')->relationship('campaign', 'name')->required()->searchable()->preload(),
                F\TextInput::make('category')->required()->maxLength(100)->default('General'),
                F\Select::make('type')->label('Posting format')->options(TokenType::mediaOptions() + ['ads' => 'Ads image (legacy)', 'image' => 'Full image (legacy)'])
                    ->required()->default('IMAGE')->live()->disabled(fn ($record) => filled($record?->submission_id))
                    ->helperText('Full images are downloaded on their own; Ads images must be attached to a member’s image. Funded adverts keep the format paid for.'),
                F\TextInput::make('capacity')->label('Maximum participants')->integer()->minValue(1)->required(),
                F\DateTimePicker::make('valid_until')->required()->default(now()->addWeek()),
                F\TagsInput::make('badge')->default(['Sponsored']),
                F\Textarea::make('description')->required()->columnSpanFull(),
            ]),
            F\Section::make('Published media')->columns(2)->schema([
                F\FileUpload::make('image_path')->label('Image / video thumbnail')->disk('admin_media')->directory('uploads')->image()->maxSize(20480)
                    ->required(fn (Get $get) => ! in_array($get('type'), ['TEXT']))->downloadable()->openable(),
                F\FileUpload::make('video_path')->label('Video')->disk('admin_media')->directory('uploads')->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm'])->maxSize(51200)
                    ->required(fn (Get $get) => $get('type') === 'VIDEO')->downloadable()->openable(),
            ]),
            F\Section::make('Target audience')->schema([
                F\Repeater::make('target_audience')->schema([
                    F\Select::make('county_id')->label('County')->options(fn () => \App\Models\Counties::pluck('name', 'id'))->live()->searchable(),
                    F\Select::make('sub_county_id')->label('Subcounty')->options(fn (Get $get) => \App\Models\SubCounty::where('county_id', $get('county_id'))->pluck('name', 'id'))->searchable(),
                    F\Select::make('gender')->options(['Male' => 'Male', 'Female' => 'Female']),
                ])->columns(3)->defaultItems(0),
            ])->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with('campaign')->withCount([
            'screenshots', 'screenshots as completions_count' => fn ($query) => $query->where('number', 2),
        ]))->columns([
            C\ImageColumn::make('image_path')->disk('admin_media')->label('Creative')->square()->size(56),
            C\TextColumn::make('name')->searchable()->sortable()->weight('semibold')->description(fn ($record) => $record->campaign?->name),
            C\TextColumn::make('type')->label('Posting format')->badge()->formatStateUsing(fn ($state) => TokenType::mediaOptions()[TokenType::normalizeMediaType($state ?? 'IMAGE')] ?? $state),
            C\TextColumn::make('capacity')->label('Capacity')->numeric()->sortable(),
            C\TextColumn::make('completions_count')->label('Completed')->numeric(),
            C\TextColumn::make('screenshots_count')->label('Screenshots')->numeric(),
            C\TextColumn::make('valid_until')->dateTime()->sortable(),
            C\TextColumn::make('availability')->state(fn ($record) => $record->valid_until >= now() ? 'Live' : 'Ended')->badge()->color(fn ($state) => $state === 'Live' ? 'success' : 'gray'),
        ])->filters([
            T\SelectFilter::make('campaign_id')->relationship('campaign', 'name')->searchable()->label('Campaign'),
            T\SelectFilter::make('type')->options(TokenType::mediaOptions())->query(function ($query, array $data) {
                return $query->when($data['value'] ?? null, fn ($query, $type) => $query->whereIn('type', match ($type) {
                    TokenType::IMAGE => ['IMAGE', 'image', 'FULL_IMAGE'],
                    TokenType::ADS_IMAGE => ['ADS_IMAGE', 'ads', 'ADS'],
                    default => [$type],
                }));
            }),
            T\Filter::make('live')->query(fn ($query) => $query->where('valid_until', '>=', now())),
        ])->actions([
            A\EditAction::make(),
            A\Action::make('screenshots')->url(fn ($record) => ScreenshotsResource::getUrl('index', ['tableFilters' => ['advert_id' => ['value' => $record->id]]])),
            A\Action::make('preview')->icon('heroicon-o-eye')->visible(fn ($record) => filled($record->image_path))
                ->url(fn ($record) => asset('storage/'.$record->image_path))->openUrlInNewTab(),
            A\Action::make('repost')->label('Repost advert')->requiresConfirmation()->form([
                F\DateTimePicker::make('valid_until')->required()->minDate(now())->default(now()->addDay()),
                F\TextInput::make('capacity')->integer()->minValue(1)->required()->default(fn ($record) => $record->capacity),
            ])->action(fn ($record, array $data) => AdminWorkflow::run(\App\Http\Controllers\ProductController::class, 'repostAdvertProducts', ['advert_id' => $record->id] + $data)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListAdvertImages::route('/'), 'create' => Pages\CreateAdvertImages::route('/create'), 'edit' => Pages\EditAdvertImages::route('/{record}/edit')];
    }
}
