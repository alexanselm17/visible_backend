<?php

namespace App\Filament\Pages;

use App\Models\TokenType;
use App\Services\TokenService;
use App\Services\TokenUsageService;
use App\Support\AdminAccess;
use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Page;

class TokenEstimator extends Page
{
    use \App\Filament\Support\NotifiesWorkflowErrors;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Tokens';

    protected static ?string $navigationLabel = 'Token estimator';

    protected static string $view = 'filament.pages.token-estimator';

    public ?array $data = [];

    public ?array $quote = null;

    public static function canAccess(): bool
    {
        return AdminAccess::allows('finance');
    }

    public function mount(): void
    {
        $this->form->fill(['mode' => 'usage', 'token_type' => 'PLATINUM', 'target_reach' => 100, 'quantity' => 10]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            F\Select::make('mode')->options(['usage' => 'Advert submission cost', 'purchase' => 'Token purchase price'])->required()->live(),
            F\Select::make('token_type')->label('Token')->options(fn () => TokenType::where('is_active', true)->pluck('name', 'code'))->required()->live(),
            F\TextInput::make('target_reach')->integer()->minValue(1)->maxValue(2000000000)->required()
                ->visible(fn (Get $get) => $get('mode') === 'usage'),
            F\TextInput::make('video_duration_seconds')->integer()->minValue(1)->required()
                ->visible(fn (Get $get) => $get('mode') === 'usage' && $get('token_type') === 'GOLD'),
            F\TextInput::make('quantity')->integer()->minValue(1)->maxValue(100000)->required()
                ->visible(fn (Get $get) => $get('mode') === 'purchase'),
        ])->columns(2)->statePath('data');
    }

    public function calculate(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $tokens = app(TokenService::class);
        if ($data['mode'] === 'purchase') {
            $quote = $tokens->quote($data['token_type'], (int) $data['quantity']);
            $this->quote = [
                'Token' => $quote['token_type'], 'Quantity' => $quote['quantity'],
                'Price per token' => $quote['currency'].' '.$quote['unit_price'],
                'Total price' => $quote['currency'].' '.$quote['total_amount'],
            ];

            return;
        }
        $type = $tokens->getTypeByCode($data['token_type']);
        $quote = app(TokenUsageService::class)->quote($type, (int) $data['target_reach'], isset($data['video_duration_seconds']) ? (int) $data['video_duration_seconds'] : null);
        $this->quote = [
            'Token' => $quote['token_type'], 'Target reach' => $quote['target_reach'],
            'Submission tokens' => $quote['submission_tokens'], 'Reach tokens' => $quote['reach_tokens'],
            'Total tokens required' => $quote['tokens_required'],
        ];
    }
}
