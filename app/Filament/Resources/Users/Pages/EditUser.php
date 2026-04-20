<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Mutates the model data before it is filled into the edit form.
     * This acts as the bridge between Spatie's relationship and the standalone 'role' Select field.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->getRecord();

        // Extract the user's primary Spatie role and inject it into the form data for the UI
        $data['role'] = $record->getRoleNames()->first();

        return $data;
    }

    /**
     * Mutates the form data immediately before it is saved back to the database.
     * We use this to enforce multi-tenant structural rules.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $authUser */
        $authUser = Auth::user();

        // 1. COMPANY-LEVEL STRUCTURAL DEFINITION:
        // By our business rules, if a user is promoted or edited to become a 'company_admin',
        // their store assignment must be wiped. They serve the whole company.
        if (isset($data['role']) && $data['role'] == Roles::COMPANY_ADMIN->value) {
            $data['store_id'] = null;
        }

        // 2. STORE-LEVEL ENFORCEMENT & IMMUTABILITY:
        // A store-level user (like a Store Manager) is strictly forbidden from transferring
        // staff to a different store. We forcefully reset the `store_id` back to the record's
        // current store to guard against malicious form injection.
        if ($authUser->isStoreLevel()) {
            $data['store_id'] = $this->getRecord()->store_id;
        }

        return $data;
    }

    /**
     * Hook that runs immediately after the updated User record is written to the database.
     * Used exclusively to handle manual Role synchronization and security actions (transfers).
     */
    protected function afterSave(): void
    {
        // Extract the role safely from the submitted form data
        $role = $this->data['role'] ?? null;
        /** @var User $record */
        $record = $this->record;

        if ($role) {
            // Because Super Admins have their own entirely separate panel and resources,
            // we NEVER have to worry about cross-company pollution here. The currently authenticated
            // user is guaranteed to be a Company/Store user, and our persistent Livewire middleware
            // automatically scopes Spatie to their company context behind the scenes.
            // Ergo, `setPermissionsTeamId` is redundant and removed. We confidently sync directly.
            $record->syncRoles([$role]);
        }
    }
}
