<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        return $schema
            ->components([
                Section::make(__('app.user_details'))
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('app.full_name')),

                        TextEntry::make('email')
                            ->label(__('app.email_address')),

                        TextEntry::make('phone')
                            ->label(__('app.phone'))
                            ->placeholder('—'),

                        IconEntry::make('is_active')
                            ->label(__('app.active'))
                            ->boolean(),

                        SpatieMediaLibraryImageEntry::make('avatar')
                            ->label(__('app.avatar'))
                            ->collection('avatar')
                            ->circular()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('app.role_and_assignment'))
                    ->schema([
                        // The 'role' field is not a direct DB column — it lives in Spatie's
                        // model_has_roles pivot table. We use state() with a Closure to pull
                        // it out at render time, then apply the same translation as the table column.
                        TextEntry::make('role')
                            ->label(__('app.role'))
                            ->badge()
                            ->state(fn (User $record): ?string => $record->getRoleNames()->first())
                            ->formatStateUsing(fn (?string $state): string => $state ? __('roles.'.$state) : '—'),

                        TextEntry::make('store.name_en')
                            ->label(__('app.assigned_store'))
                            ->placeholder(__('app.all_stores'))
                            ->visible(fn (): bool => ! $authUser->isStoreLevel()),
                    ])
                    ->columns(2),
            ]);
    }
}
