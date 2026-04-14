<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Intercepts and mutates the form data before the new User record is created in the database.
     * This is crucial for enforcing our multi-tenant hierarchy and ensuring data integrity
     * based on the creator's structural level (Company vs. Store).
     *
     * @param  array  $data  The validated data from the form
     * @return array The mutated data ready for database insertion
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the currently authenticated user creating the new record
        /** @var User $authUser */
        $authUser = Auth::user();

        // 1. TENANT ISOLATION:
        // We ensure that users are always created within the same company as the admin creating them.
        // Even though Super Admins have a separate panel, this acts as a foolproof safeguard
        // against any unintended cross-tenant user creation if a Super Admin accesses this logic.
        if (! $authUser->isSuperAdmin()) {
            $data['company_id'] = $authUser->company_id;
        }

        // 2. COMPANY-LEVEL STRUCTURAL DEFINITION:
        // By our business rules, a "Company Level User" is defined structurally by having no store_id.
        // Therefore, if the selected role is exactly 'company_admin', we forcefully set 'store_id' to null.
        // This prevents accidental pinning of a company-wide administrator to a specific branch.
        if (isset($data['role']) && $data['role'] == Roles::COMPANY_ADMIN->value) {
            $data['store_id'] = null;
        }

        // 3. STORE-LEVEL ENFORCEMENT & IMMUTABILITY:
        // If the admin creating this user operates at the store level (e.g., a Store Manager),
        // they are structurally bound to their specific store. We aggressively override the
        // 'store_id' of the user being created to match the creator's 'store_id'.
        // This ensures branch staff cannot secretly create users for other branches.
        if ($authUser->isStoreLevel()) {
            $data['store_id'] = $authUser->store_id;
        }

        // Return the hardened data payload to Filament for saving
        return $data;
    }

    /**
     * Hook that runs immediately after the new User record is written to the database.
     * We use this to manually handle role synchronization, since 'role' in our form
     * is a standalone UI field, not a direct Eloquent column or basic relationship.
     */
    protected function afterCreate(): void
    {
        // Extract the role selected in the UI form. Using the null coalescing operator
        // prevents errors if the role input somehow wasn't provided.
        $role = $this->data['role'] ?? null;

        if ($role) {
            // Retrieve the newly persisted User model instance from Filament
            /** @var User $record */
            $record = $this->record;

            // Using Spatie Laravel Parameters:
            // We do NOT need to call `setPermissionsTeamId()` here.
            // Our app relies on a persistent Livewire middleware to set the team (company) ID globally.
            // Furthermore, Super Admins have their own dedicated panel, so we are guaranteed
            // that this action is occurring entirely within a homogenous company context.
            // We just sync the given role directly, and Spatie attaches it to the current team.
            $record->syncRoles([$role]);
        }
    }
}
