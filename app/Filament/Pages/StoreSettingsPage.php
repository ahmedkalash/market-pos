<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Stores\Schemas\StoreSchema;
use App\Models\Store;
use BackedEnum;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class StoreSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.store-settings';

    public ?Store $store = null;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('store_settings.title');
    }

    public function getTitle(): string
    {
        return __('store_settings.title');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->isStoreLevel() && Auth::user()?->can('manage_store_settings');
    }

    public function mount(): void
    {
        $this->store = Auth::user()->store;

        if (! $this->store) {
            abort(404);
        }

        $this->form->fill($this->store->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return StoreSchema::configure($schema)
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            Auth::user()->store->update($data);

            Notification::make()
                ->title(__('store_settings.settings_updated_successfully'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('store_settings.error_updating_settings'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
