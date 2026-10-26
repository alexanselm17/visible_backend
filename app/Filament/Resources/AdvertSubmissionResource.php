<?php

namespace App\Filament\Resources;

use App\Filament\Support\Fields;
use App\Filament\Support\OperationsResource;
use App\Http\Controllers\Api\CampaignOwner\TokenOnlyAdvertSubmissionController as Workflow;
use App\Models\AdvertSubmission;
use App\Support\AdminWorkflow;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdvertSubmissionResource extends OperationsResource
{
    protected static ?string $model = AdvertSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';

    protected static ?string $navigationGroup = 'Campaigns';

    protected static ?string $navigationLabel = 'Submission workflow';

    protected static ?int $navigationSort = 1;

    protected static string $area = 'campaigns';

    protected static array $exportColumns = ['id', 'name', 'user.fullname', 'type', 'status', 'target_reach', 'tokens_reserved', 'tokens_spent', 'created_at'];

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Section::make('Campaign brief')->columns(2)->schema([
                F\TextInput::make('name'), F\TextInput::make('campaign.name')->label('Campaign'),
                F\TextInput::make('user.fullname')->label('Owner'), F\TextInput::make('type'),
                F\Textarea::make('description')->columnSpanFull(),
                F\Placeholder::make('audience')->label('Target audience')->columnSpanFull()
                    ->content(fn ($record) => view('filament.components.json-details', ['value' => $record?->target_audience])),
                F\TextInput::make('target_reach'), F\TextInput::make('status'),
            ]),
            F\Section::make('Token reservation & final design')->columns(3)->schema([
                F\TextInput::make('tokenType.code')->label('Token'),
                F\TextInput::make('tokens_reserved'), F\TextInput::make('tokens_spent'),
                F\TextInput::make('video_duration_seconds'), F\TextInput::make('final_video_duration_seconds'),
                F\TextInput::make('people_per_token_snapshot'), F\TextInput::make('seconds_per_token_snapshot'),
                F\TextInput::make('designer.fullname')->label('Designer'), F\TextInput::make('designed_at'),
                F\Textarea::make('rejection_reason')->columnSpanFull(),
            ]),
            F\Section::make('Media files')->schema([
                F\Placeholder::make('media_preview')->hiddenLabel()->content(fn ($record) => view('filament.components.submission-media', ['submission' => $record])),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->modifyQueryUsing(fn ($query) => $query->with(['user', 'campaign', 'tokenType', 'media', 'designer']))
            ->columns([
                C\TextColumn::make('name')->searchable()->sortable()->description(fn ($record) => $record->campaign?->name)->weight('semibold'),
                C\TextColumn::make('user.fullname')->label('Owner')->searchable(),
                C\TextColumn::make('type')->badge(), Fields::status(),
                C\TextColumn::make('target_reach')->label('Reach')->numeric()->sortable(),
                C\TextColumn::make('tokens_reserved')->label('Reserved')->numeric(),
                C\TextColumn::make('tokens_spent')->label('Spent')->numeric(),
                C\TextColumn::make('created_at')->since()->label('Submitted')->sortable(),
            ])->filters([
                SelectFilter::make('status')->options(array_combine(\App\Enums\AdvertSubmissionStatus::all(), \App\Enums\AdvertSubmissionStatus::all())),
                SelectFilter::make('type')->options(['IMAGE' => 'Image', 'VIDEO' => 'Video', 'TEXT' => 'Text']),
                SelectFilter::make('submitted_by')->relationship('user', 'fullname')->searchable()->label('Owner'),
                SelectFilter::make('designed_by')->relationship('designer', 'fullname')->searchable()->label('Designer'),
                SelectFilter::make('reviewed_by')->relationship('reviewer', 'fullname')->searchable()->label('Reviewer'),
            ])->actions([
                A\ViewAction::make()->label('Brief & files')->modalWidth('5xl'),
                A\ActionGroup::make([
                    A\Action::make('approve')->color('success')->icon('heroicon-o-check')->requiresConfirmation()
                        ->modalDescription('Reserve remains in place. Images and videos move to design; text becomes ready to publish.')
                        ->visible(fn ($record) => $record->status === 'PENDING_APPROVAL')
                        ->action(fn ($record) => AdminWorkflow::run(Workflow::class, 'approve', parameters: ['submissionId' => $record->id])),
                    A\Action::make('final_design')->label('Upload final design')->icon('heroicon-o-arrow-up-tray')
                        ->visible(fn ($record) => $record->status === 'PENDING_DESIGN')
                        ->form([
                            F\FileUpload::make('final_image')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(10240)->storeFiles(false)
                                ->required(fn ($record) => $record->type === 'IMAGE')->visible(fn ($record) => $record->type === 'IMAGE'),
                            F\FileUpload::make('final_video')->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'])->maxSize(51200)->storeFiles(false)
                                ->required(fn ($record) => $record->type === 'VIDEO')->visible(fn ($record) => $record->type === 'VIDEO'),
                            F\FileUpload::make('thumbnail_image')->image()->maxSize(10240)->storeFiles(false)
                                ->required(fn ($record) => $record->type === 'VIDEO')->visible(fn ($record) => $record->type === 'VIDEO'),
                            F\TextInput::make('final_video_duration_seconds')->integer()->minValue(1)
                                ->required(fn ($record) => $record->type === 'VIDEO')->visible(fn ($record) => $record->type === 'VIDEO'),
                            Fields::user('designer_id', 'Designer')->required()->default(fn () => auth()->id()),
                        ])->action(function ($record, array $data) {
                            $files = array_filter(array_intersect_key($data, array_flip(['final_image', 'final_video', 'thumbnail_image'])));

                            return AdminWorkflow::run(Workflow::class, 'uploadFinalDesign', array_diff_key($data, $files), ['submissionId' => $record->id], $files);
                        }),
                    A\Action::make('publish')->label('Publish advert')->color('success')->icon('heroicon-o-paper-airplane')
                        ->visible(fn ($record) => $record->status === 'DESIGN_DONE')->requiresConfirmation()
                        ->modalDescription('Final token usage will be settled. Extra tokens may be needed if video duration increased.')
                        ->form([
                            F\TextInput::make('category')->default('General')->maxLength(100),
                            F\TagsInput::make('badge')->required()->default(['Sponsored']),
                            F\DateTimePicker::make('valid_until')->required()->minDate(now())->default(now()->addWeek()),
                            F\Textarea::make('description')->required()->default(fn ($record) => $record->description),
                        ])->action(fn ($record, array $data) => AdminWorkflow::run(Workflow::class, 'rolloutSubmission', $data, ['submissionId' => $record->id])),
                    A\Action::make('reject')->color('danger')->icon('heroicon-o-x-mark')
                        ->visible(fn ($record) => ! in_array($record->status, ['PUBLISHED', 'REJECTED']))
                        ->form([F\Textarea::make('reason')->required()->minLength(5)->maxLength(1000)])
                        ->requiresConfirmation()->modalDescription('Reserved tokens will be returned to the owner.')
                        ->action(fn ($record, array $data) => AdminWorkflow::run(Workflow::class, 'reject', $data, ['submissionId' => $record->id])),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => AdvertSubmissionResource\Pages\ManageAdvertSubmissions::route('/')];
    }
}
