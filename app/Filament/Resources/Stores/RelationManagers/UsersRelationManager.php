<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Enums\Roles;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use App\Filament\Resources\Users\UserResource;
use App\Events\UserTransferred;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Spatie\Permission\Models\Role;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';


    /**
     * @param Model $ownerRecord
     * @param string $pageClass
     * @return string
     */
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('store_settings.users');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordActionsColumnLabel(__('app.actions'))
            ->recordTitleAttribute('name')
            ->recordUrl(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label(__('app.email'))
                    ->searchable(),

                TextColumn::make('roles.name')
                    ->label(__('app.role'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => is_standard_role($state) ? __('roles.'.$state) : $state),

                IconColumn::make('is_active')
                    ->label(__('app.active'))
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('app.role'))
                    ->relationship('roles', 'name', function (Builder $query) {
                        return $query->where('roles.company_id', auth()->user()->company_id)
                            ->where('name', '!=', Roles::COMPANY_ADMIN->value);
                    })
                    ->getOptionLabelFromRecordUsing(fn (Role $record) => is_standard_role($record->name) ? __('roles.'.$record->name) : $record->name)
                    ->multiple()
                    ->preload(),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->visible(fn () => auth()->user()->can('update_store'))
                    ->label(__('store_settings.actions.add_user_to_store'))
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query
                        ->whereDoesntHave('roles', fn ($q) => $q->where('name', Roles::COMPANY_ADMIN->value))
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label(__('filament-actions::edit.single.label'))
                        ->icon(Heroicon::PencilSquare)
                        ->color('primary')
                        ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
                    DissociateAction::make()
                        ->visible(fn () => auth()->user()->can('update_store'))
                        ->label(__('store_settings.actions.dissociate_user_from_store')),

                ])

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make()
                        ->visible(fn () => auth()->user()->can('update_user'))
                        ->label(__('store_settings.actions.dissociate_user_from_store')),
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->can('delete_user')),
                ]),
            ]);
    }
}
