<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;

class Fields
{
    public static function advert(string $field = 'advert_id'): Select
    {
        return Select::make($field)->label('Advert')->searchable()
            ->getSearchResultsUsing(fn (string $search) => \App\Models\AdvertImages::where('name', 'like', "%{$search}%")->latest()->limit(50)->pluck('name', 'id')->all())
            ->getOptionLabelUsing(fn ($value) => \App\Models\AdvertImages::find($value)?->name ?? $value);
    }

    public static function user(string $field = 'user_id', string $label = 'Member'): Select
    {
        return Select::make($field)->label($label)->searchable()
            ->getSearchResultsUsing(fn (string $search) => User::where(fn ($q) => $q->where('fullname', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"))
                ->limit(50)->get()->mapWithKeys(fn ($u) => [$u->id => "{$u->fullname} · {$u->username}"])->all())
            ->getOptionLabelUsing(fn ($value) => User::find($value)?->fullname ?? $value);
    }

    public static function status(string $field = 'status'): TextColumn
    {
        return TextColumn::make($field)->badge()->formatStateUsing(fn ($state) => str($state)->replace('_', ' ')->title())
            ->color(fn ($state) => match (strtolower((string) $state)) {
                'paid', 'active', 'published', 'design_done', 'settled' => 'success',
                'failed', 'rejected', 'suspended', 'cancelled', 'confirmed' => 'danger',
                'pending', 'pending_approval', 'pending_design', 'payment_pending', 'processing' => 'warning',
                default => 'gray',
            });
    }

    public static function money(string $field, string $label): TextColumn
    {
        return TextColumn::make($field)->label($label)->sortable()
            ->formatStateUsing(fn ($state, $record) => $record->currency.' '.number_format(((int) $state) / 100, 2));
    }
}
