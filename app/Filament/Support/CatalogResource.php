<?php

namespace App\Filament\Support;

use Filament\Forms\Components as F;
use Filament\Forms\Form;
use Filament\Infolists\Components as I;
use Filament\Tables\Actions as A;
use Filament\Tables\Columns as C;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

abstract class CatalogResource extends OperationsResource
{
    // [attribute, label, type, options]. Secrets are deliberately absent from this catalog.
    protected static array $fields = [];

    protected static array $tableFields = [];

    protected static array $statusOptions = [];

    public static function form(Form $form): Form
    {
        return $form->schema(array_map(function ($field) {
            [$name, $label, $type] = $field;
            $input = match ($type) {
                'bool' => F\Toggle::make($name),
                'select' => F\Select::make($name)->options($field[3])->required(),
                'user' => Fields::user($name, $label)->required(),
                'json' => F\KeyValue::make($name)->columnSpanFull(),
                'text' => F\Textarea::make($name)->maxLength(2000),
                'date' => F\DateTimePicker::make($name),
                'number' => F\TextInput::make($name)->numeric()->minValue(0)->required(),
                default => F\TextInput::make($name)->required()->maxLength(255),
            };
            if ($type === 'unique') {
                $input->unique(ignoreRecord: true);
            }
            if ($name === 'slug') {
                $input->disabled(fn ($record) => $record !== null)->dehydrated(fn ($record) => $record === null);
            }

            return $input->label($label);
        }, static::$fields))->columns(2);
    }

    public static function table(Table $table): Table
    {
        $fields = static::$tableFields ?: static::$fields;
        $columns = array_map(function ($field) {
            [$name, $label, $type] = $field;
            if ($type === 'bool') {
                return C\IconColumn::make($name)->label($label)->boolean();
            }
            if ($type === 'status') {
                return Fields::status($name)->label($label);
            }
            if ($type === 'minor') {
                return Fields::money($name, $label);
            }
            $column = C\TextColumn::make($name)->label($label)->placeholder('—')->limit(60);

            return match ($type) {
                'date' => $column->dateTime()->sortable(),
                'number' => $column->numeric()->sortable(),
                'json' => $column->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : $state)->toggleable(isToggledHiddenByDefault: true),
                default => $column->searchable()->wrap(),
            };
        }, $fields);

        return parent::table($table)->columns($columns)
            ->filters(static::$statusOptions ? [SelectFilter::make('status')->options(static::$statusOptions)] : [])
            ->actions([
                A\ViewAction::make()->modalWidth('4xl')->infolist(array_map(fn ($field) => $field[2] === 'json'
                    ? I\TextEntry::make($field[0])->label($field[1])->columnSpanFull()
                        ->state(fn ($record) => json_encode(data_get($record, $field[0]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->prose()
                    : I\TextEntry::make($field[0])->label($field[1])->placeholder('—')->formatStateUsing(fn ($state) => is_bool($state) ? ($state ? 'Yes' : 'No') : $state), $fields)),
                A\EditAction::make()->visible(fn ($record) => static::canEdit($record)),
            ]);
    }
}
