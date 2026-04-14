<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
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
            ->recordUrl(null)
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

                        // 1. Enforce Company Admin Protection
                        // Only top-level administrators (Company Admins & Super Admins)
                        // have the authority to even see or filter by the 'company_admin' role.
                        if (! $authUser->isCompanyAdmin() && ! $authUser->isSuperAdmin()) {
                            $query->where('name', '!=', Roles::COMPANY_ADMIN->value);
                        }

                        // 2. Enforce Store Manager Protection
                        // In the store level, only a Store Manager can see or filter by the 'store_manager' role.
                        // Lower-level store staff (like Cashiers or Stock Clerks) are shielded from seeing this role.
                        // Note: In the company level, Company Admins (and other authorized company-level staff)
                        // bypass this block and CAN see/filter by the 'store_manager' role.
                        if ($authUser->isStoreLevel() && ! $authUser->isStoreManager()) {
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
                ActionGroup::make([
                    ViewAction::make()
                        ->visible(fn ($record) => UserResource::canView($record)),
                    EditAction::make()
                        ->visible(fn ($record) => UserResource::canEdit($record)),
                    DeleteAction::make()
                        ->visible(fn ($record) => UserResource::canDelete($record)),
                ])->badge(),

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
