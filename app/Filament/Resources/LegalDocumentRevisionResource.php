<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\LegalDocumentRevision;
use App\Services\LegalPageService;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class LegalDocumentRevisionResource extends OperationsResource
{
    protected static ?string $model = LegalDocumentRevision::class;

    protected static ?string $navigationLabel = 'Policy publication history';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            C\TextColumn::make('title'), C\TextColumn::make('version')->badge(),
            C\TextColumn::make('effective_on')->date(), C\TextColumn::make('created_at')->label('Published')->dateTime(),
            C\TextColumn::make('published_by')->label('Publisher ID')->copyable(),
        ])->actions([
            A\Action::make('read')->label('Read published version')->modalHeading(fn ($record) => $record->title.' · v'.$record->version)
                ->modalContent(fn ($record) => new HtmlString('<article class="prose dark:prose-invert max-w-none">'.app(LegalPageService::class)->sanitize($record->content).'</article>'))
                ->modalSubmitAction(false)->modalCancelActionLabel('Close')->modalWidth('5xl'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => LegalDocumentRevisionResource\Pages\ManageLegalDocumentRevisions::route('/')];
    }
}
