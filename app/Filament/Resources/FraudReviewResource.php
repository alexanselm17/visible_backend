<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class FraudReviewResource extends CatalogResource
{
    protected static ?string $model = \App\Models\FraudReview::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Trust & verification';

    protected static ?string $navigationLabel = 'Fraud review history';

    protected static string $area = 'fraud';

    protected static bool $editable = false;

    protected static array $fields = [['campaign.name', 'Campaign', 'string'], ['advert.name', 'Advert', 'string'], ['pattern_key', 'Pattern', 'string'], ['status', 'Decision', 'status'], ['notes', 'Notes', 'string'], ['reviewer.fullname', 'Reviewer', 'string'], ['reviewed_at', 'Reviewed', 'date'], ['fraud_payload', 'Evidence', 'json']];

    protected static array $exportColumns = ['campaign.name', 'advert.name', 'pattern_key', 'status', 'notes', 'reviewer.fullname', 'reviewed_at', 'fraud_payload'];

    public static function getPages(): array
    {
        return ['index' => FraudReviewResource\Pages\ManageFraudReviews::route('/')];
    }
}
