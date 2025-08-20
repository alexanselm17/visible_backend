<?php

namespace App\Filament\Widgets;

use App\Models\Screenshots;
use App\Models\AdvertImages;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Actions\Action;


class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Screenshot Submissions';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Screenshots::query()
                    ->with(['user', 'advert.campaign'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                ImageColumn::make('image')
                    ->label('Image')
                    ->circular()
                    ->size(60)
                    ->getStateUsing(function ($record) {
                        $path = $record->screenshot;

                        return $path
                            ? asset('storage/' . $path)
                            : asset('storage/products/default-product.png');
                    })
                    ->action(
                        Action::make('preview')
                            ->label('') // no button label, just clickable image
                            ->modalHeading('Preview Image')
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalWidth('sm')
                            ->modalContent(
                                fn($record) =>
                                view('filament.components.image-preview', [
                                    'url' => $record->screenshot
                                        ? asset('storage/' . $record->screenshot)
                                        : asset('storage/products/default-product.png'),
                                ])
                            )
                    ),


                Tables\Columns\TextColumn::make('user.fullname')
                    ->label('User')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('advert.name')
                    ->label('Advertisement')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('advert.campaign.name')
                    ->label('Campaign')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
