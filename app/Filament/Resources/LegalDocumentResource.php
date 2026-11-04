<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\LegalDocument;
use App\Services\LegalPageService;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;

class LegalDocumentResource extends OperationsResource
{
    protected static ?string $model = LegalDocument::class;

    protected static ?string $navigationLabel = 'Legal policies';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $editable = true;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            F\Placeholder::make('instructions')->content('Save edits as a draft, preview, then publish. Public URLs stay fixed. Changes to billing/permissions still need Android implementation and the correct Play Console declarations.'),
            F\TextInput::make('title')->required()->maxLength(255),
            F\DatePicker::make('effective_on')->label('Effective date')->required()->maxDate(today()),
            F\RichEditor::make('draft_content')->label('Policy content')->required()->maxLength(150000)
                ->toolbarButtons(['bold', 'italic', 'h2', 'h3', 'bulletList', 'orderedList', 'link', 'blockquote', 'undo', 'redo'])
                ->helperText('Supported placeholders: [APP_NAME], [OPERATOR_NAME], [ACCESSIBILITY_DISCLOSURE], [RETENTION_POLICY]. They are replaced safely on publication.')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            C\TextColumn::make('title')->searchable(),
            C\TextColumn::make('slug')->label('Public URL path')->formatStateUsing(fn ($state) => '/'.$state)->copyable(),
            C\TextColumn::make('version')->label('Published version')->formatStateUsing(fn ($state) => $state ? 'v'.$state : 'Draft only')->badge(),
            C\TextColumn::make('published_at')->dateTime()->placeholder('Not published'),
            C\TextColumn::make('updated_at')->label('Draft last edited')->dateTime(),
        ])->actions([
            A\EditAction::make()->label('Edit draft')->modalWidth('5xl')->mutateFormDataUsing(function (array $data) {
                $data['draft_content'] = app(LegalPageService::class)->sanitize($data['draft_content']);
                $data['updated_by'] = auth()->id();

                return $data;
            }),
            A\Action::make('preview')->label('Preview draft')->icon('heroicon-o-eye')->url(fn ($record) => route('legal.preview', $record))->openUrlInNewTab(),
            A\Action::make('publish')->icon('heroicon-o-globe-alt')->color('success')->form([
                F\Placeholder::make('checklist')->content('Confirm the text matches the shipped app, provider disclosures, actual retention/deletion process and supported billing. This does not certify Play approval or replace legal review.'),
                F\Checkbox::make('reviewed')->label('I have reviewed the policy and confirmed these facts.')->accepted()->required(),
            ])->action(function (LegalDocument $record) {
                app(LegalPageService::class)->publish($record);
                Notification::make()->title('Policy published')->body('The public URL now serves the new version. The previous version remains in publication history.')->success()->send();
            }),
            A\Action::make('public')->label('Public page')->url(fn ($record) => url('/'.$record->slug))->openUrlInNewTab(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return ['index' => LegalDocumentResource\Pages\ManageLegalDocuments::route('/')];
    }
}
