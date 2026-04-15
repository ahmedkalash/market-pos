<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\Roles;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Stores the user's original store_id before any changes are saved.
     * We need this to detect if a "store transfer" occurred, triggering session invalidation.
     */
    public ?int $originalStoreId = null;

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
     * Hook that runs before the save transaction begins.
     * We capture the original store_id here because once the record saves,
     * $record->store_id will reflect the new value.
     */
    protected function beforeSave(): void
    {
        $this->originalStoreId = $this->getRecord()->store_id;
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

        // DETECT STORE TRANSFER & ENFORCE SECURITY
        // If the 'originalStoreId' differs from the newly saved 'store_id', a structural transfer occurred.
        // It is a massive security risk to leave a transferred user's active sessions open in their old store.
        if ($this->originalStoreId !== $record->store_id) {

            // 1. INVALIDATE ACTIVE SESSIONS
            if (config('session.driver') === 'database') {
                try {
                    // Wipe this user off all active devices instantly
                    DB::table('sessions')
                        ->where('user_id', $record->id)
                        ->delete();
                } catch (\Exception $e) {
                    // Silent fail but log it for DevOps tracking
                    Log::error('Failed to revoke session on transfer: '.$e->getMessage());
                }
            }

            // 2. NOTIFY THE USER
            // Using Filament's database notifications to leave a papertrail that they were transferred.
            // On their next login (since sessions were killed), they will see this alert.
            Notification::make()
                ->title(__('app.store_assignment_changed'))
                ->body(__('app.store_assignment_changed_body'))
                ->warning()
                ->sendToDatabase($record);
        }
    }
}
