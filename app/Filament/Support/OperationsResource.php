<?php

namespace App\Filament\Support;

use App\Support\AdminAccess;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class OperationsResource extends Resource
{
    protected static string $area = 'settings';

    protected static bool $editable = false;

    protected static array $exportColumns = [];

    public static function canViewAny(): bool
    {
        return AdminAccess::allows(static::$area);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::$editable && static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::$editable && static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([15, 30, 60, 100])
            ->emptyStateHeading('No records to show')
            ->emptyStateDescription('New records will appear here as this workflow is used.')
            ->filters([
                Filter::make('created_at')->label('Created between')->form([
                    DatePicker::make('from')->label('From'),
                    DatePicker::make('until')->label('Until')->afterOrEqual('from'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'], fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
                    ->when($data['until'], fn ($q, $value) => $q->whereDate('created_at', '<=', $value))),
            ])
            ->headerActions([
                Action::make('export')->label('Export filtered CSV')->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn () => static::canViewAny() && count(static::$exportColumns) > 0)
                    ->action(function ($livewire) {
                        abort_unless(static::canViewAny(), 403);
                        $query = clone $livewire->getFilteredTableQuery();

                        return response()->streamDownload(function () use ($query) {
                            $stream = fopen('php://output', 'w');
                            fputcsv($stream, static::$exportColumns);
                            foreach ($query->lazy(500) as $record) {
                                fputcsv($stream, array_map(function ($column) use ($record) {
                                    $value = data_get($record, $column);
                                    $value = is_array($value) ? json_encode($value) : (string) $value;

                                    return preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                                }, static::$exportColumns));
                            }
                            fclose($stream);
                        }, static::getSlug().'-'.now()->format('Y-m-d').'.csv');
                    }),
            ]);
    }
}
