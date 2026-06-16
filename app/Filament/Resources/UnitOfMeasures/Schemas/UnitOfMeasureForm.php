<?php

namespace App\Filament\Resources\UnitOfMeasures\Schemas;

use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UnitOfMeasureForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user();

        return $schema
            ->components([
                Select::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', lang_suffix('name'), fn (Builder $query, ?Model $record) => $query->active($record?->store_id))
                    ->required(fn () => $user->isCompanyLevel())
                    ->searchable(['name_en', 'name_ar'])
                    ->preload()
                    ->visible(fn () => $user->isCompanyLevel()),
                Hidden::make('store_id')
                    ->default(fn () => $user->store_id)
                    ->visible(fn () => $user->isStoreLevel()),
                TextInput::make('name_ar')
                    ->label(__('unit_of_measure.name_ar'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('name_en')
                    ->label(__('unit_of_measure.name_en'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('abbreviation_ar')
                    ->label(__('unit_of_measure.abbreviation_ar'))
                    ->required()
                    ->maxLength(50),
                TextInput::make('abbreviation_en')
                    ->label(__('unit_of_measure.abbreviation_en'))
                    ->required()
                    ->maxLength(50),
            ]);
    }
}
