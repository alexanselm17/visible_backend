<?php

namespace App\Filament\Resources;

use App\Filament\Support\OperationsResource;
use App\Models\PrivacyRequest;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Forms\Get;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

class PrivacyRequestResource extends OperationsResource
{
    protected static ?string $model = PrivacyRequest::class;

    protected static ?string $navigationLabel = 'Privacy requests';

    protected static ?string $navigationGroup = 'Settings';

    protected static ?string $navigationIcon = 'heroicon-o-inbox';

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny() || ! \Illuminate\Support\Facades\Schema::hasTable('privacy_requests')) {
            return null;
        }

        $count = PrivacyRequest::where('status', 'new')->count();

        return $count ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)->columns([
            C\TextColumn::make('reference')->searchable()->copyable(),
            C\TextColumn::make('type')->badge(), C\TextColumn::make('contact')->searchable()->wrap(),
            C\TextColumn::make('status')->badge(), C\TextColumn::make('created_at')->label('Received')->dateTime(),
        ])->filters([
            SelectFilter::make('status')->options(['new' => 'New', 'in_review' => 'In review', 'completed' => 'Completed', 'declined' => 'Declined with explanation']),
            SelectFilter::make('type')->options(['deletion' => 'Account/data deletion', 'access' => 'Data access', 'correction' => 'Correction', 'review' => 'Human review', 'privacy' => 'Other privacy/support']),
        ])->actions([
            A\Action::make('handle')->label('Review request')->modalWidth('3xl')->fillForm(fn ($record) => $record->toArray())->form([
                F\Placeholder::make('contact')->content(fn ($record) => $record->contact),
                F\Placeholder::make('message')->content(fn ($record) => $record->message ?: 'No additional message.'),
                F\Placeholder::make('instructions')->content('Verify ownership through the registered contact before disclosing data or taking action. The form does not delete accounts. For deletion, remove/anonymize unnecessary personal data and media, handle provider copies/backups and document any lawful retained records. A recoverable admin deletion alone is NOT fulfillment.'),
                F\Select::make('status')->required()->live()->options([
                    'new' => 'New', 'in_review' => 'In review / ownership check', 'completed' => 'Completed', 'declined' => 'Declined with explanation',
                ]),
                F\Textarea::make('staff_notes')->label('Action taken / explanation')->rows(5)->maxLength(10000)
                    ->required(fn (Get $get) => in_array($get('status'), ['completed', 'declined'], true))
                    ->helperText('Record ownership checks, actual action, response and any retained categories/reason/expiry. Do not store passwords, PINs or OTP codes.'),
                F\Checkbox::make('fulfilled')->label('Ownership was verified; the requested action is actually complete and the user was informed.')
                    ->visible(fn (Get $get) => $get('status') === 'completed')
                    ->rules(fn (Get $get) => $get('status') === 'completed' ? ['required', 'accepted'] : []),
            ])->action(function (PrivacyRequest $record, array $data) {
                abort_unless(AdminAccess::allows('settings'), 403);
                validator($data, [
                    'status' => ['required', Rule::in(['new', 'in_review', 'completed', 'declined'])],
                    'staff_notes' => [Rule::requiredIf(in_array($data['status'], ['completed', 'declined'], true)), 'nullable', 'string', 'max:10000'],
                    'fulfilled' => $data['status'] === 'completed' ? ['required', 'accepted'] : ['nullable'],
                ])->validate();
                $record->update([
                    'status' => $data['status'], 'staff_notes' => $data['staff_notes'] ?? null,
                    'processed_by' => auth()->id(), 'completed_at' => $data['status'] === 'completed' ? now() : null,
                ]);
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => PrivacyRequestResource\Pages\ManagePrivacyRequests::route('/')];
    }
}
