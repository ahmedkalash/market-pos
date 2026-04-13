<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('avatar')
                    ->label(__('app.avatar'))
                    ->collection('avatar')
                    ->circular(),

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
                    ->formatStateUsing(fn (string $state): string => __('app.roles.'.$state)),

                TextColumn::make('store.name_en')
                    ->label(__('app.store'))
                    ->placeholder(__('app.all_stores')),
                //                    ->visible($authUser->isSuperAdmin() || $authUser->isCompanyLevel()), // commented for testing

                TextColumn::make('phone')
                    ->label(__('app.phone'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label(__('app.active'))
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label(__('app.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label(__('app.role'))
                    ->relationship('roles', 'name', function ($query) use ($authUser) {
                        $query->where('roles.company_id', $authUser->company_id);

                        if (! $authUser->isCompanyAdmin() && ! $authUser->isSuperAdmin()) {
                            // Non-company admins should not see or filter by the Company Admin role
                            $query->where('name', '!=', Roles::COMPANY_ADMIN->value);
                        }

                        if ($authUser->isStoreLevel() && ! $authUser->isStoreManager()) {
                            // Store level staff should not see or filter by the Store Manager role
                            $query->where('name', '!=', Roles::STORE_MANAGER->value);
                        }

                        return $query;
                    })
                    ->getOptionLabelFromRecordUsing(fn (Role $record) => __('app.roles.'.$record->name))
                    ->multiple()
                    ->preload(),

                SelectFilter::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', 'name_en', fn ($query) => $query->where('company_id', $authUser->company_id))
                    ->visible($authUser->isSuperAdmin() || $authUser->isCompanyLevel()),

                TernaryFilter::make('is_active')
                    ->label(__('app.view_active')),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => UserResource::canEdit($record)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (Collection $records, DeleteBulkAction $action) use ($authUser) {
                            foreach ($records as $record) {
                                if (! $record->isManageableBy($authUser)) {
                                    Notification::make()
                                        ->danger()
                                        ->title(__('app.unauthorized'))
                                        // A generic message as we don't have a specific language key defined yet
                                        ->body(__('app.unauthorized_management_body'))
                                        ->send();

                                    $action->halt();
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
