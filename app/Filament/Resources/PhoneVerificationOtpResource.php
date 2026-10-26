<?php

namespace App\Filament\Resources;

use App\Filament\Support\CatalogResource;

class PhoneVerificationOtpResource extends CatalogResource
{
    protected static ?string $model = \App\Models\PhoneVerificationOtp::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationGroup = 'Trust & verification';

    protected static ?string $navigationLabel = 'Phone verification';

    protected static string $area = 'users';

    protected static bool $editable = false;

    protected static array $fields = [['user.fullname', 'Member', 'string'], ['phone', 'Phone', 'string'], ['purpose', 'Purpose', 'string'], ['attempts', 'Attempts', 'number'], ['max_attempts', 'Limit', 'number'], ['sent_at', 'Sent', 'date'], ['expires_at', 'Expires', 'date'], ['verified_at', 'Verified', 'date'], ['delivery_reference', 'Delivery reference', 'string']];

    protected static array $exportColumns = ['user.fullname', 'phone', 'purpose', 'attempts', 'max_attempts', 'sent_at', 'expires_at', 'verified_at', 'delivery_reference'];

    public static function getPages(): array
    {
        return ['index' => PhoneVerificationOtpResource\Pages\ManagePhoneVerificationOtps::route('/')];
    }
}
