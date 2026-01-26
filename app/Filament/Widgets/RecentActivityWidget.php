<?php

namespace App\Filament\Widgets;

use App\Models\Screenshots;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivityWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Screenshot Submissions';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

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
                    ->size(60)
                    ->getStateUsing(function ($record) {
                        $path = $record->screenshot;

                        return $path
                            ? asset('storage/'.$path)
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
                                fn ($record) => view('filament.components.image-preview', [
                                    'url' => $record->screenshot
                                        ? asset('storage/'.$record->screenshot)
                                        : asset('storage/products/default-product.png'),
                                ])
                            )
                    ),
                Tables\Columns\TextColumn::make('user.fullname')
                    ->label('User')
                    ->searchable()
                    ->weight('bold')
                    ->extraAttributes(['class' => 'px-3 py-2']),
                Tables\Columns\TextColumn::make('advert.name')
                    ->label('Advertisement')
                    ->searchable()
                    ->wrap()
                    ->extraAttributes(['class' => 'px-3 py-2']),
                Tables\Columns\TextColumn::make('advert.campaign.name')
                    ->label('Campaign')
                    ->badge()
                    ->color('primary')
                    ->extraAttributes(['class' => 'px-3 py-2']),
                Tables\Columns\TextColumn::make('views')
                    ->numeric()
                    ->badge()
                    ->color('success')
                    ->extraAttributes(['class' => 'px-3 py-2']),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->extraAttributes(['class' => 'px-3 py-2']),
            ])
            ->paginated(false);
    }
}
