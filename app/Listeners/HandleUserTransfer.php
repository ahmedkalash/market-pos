<?php

namespace App\Listeners;

use App\Events\UserTransferred;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HandleUserTransfer
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserTransferred $event): void
    {
        $user = $event->user;

        // 1. INVALIDATE ACTIVE SESSIONS
        // It is a security risk to leave a transferred user's active sessions open in their old store.
        if (config('session.driver') === 'database') {
            try {
                // Wipe this user off all active devices instantly
                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->delete();
            } catch (\Exception $e) {
                // Silent fail but log it for DevOps tracking
                Log::error('Failed to revoke session on transfer: '.$e->getMessage());
            }
        }

        // 2. NOTIFY THE USER
        Notification::make()
            ->title(__('app.store_assignment_changed'))
            ->body(__('app.store_assignment_changed_body'))
            ->warning()
            ->sendToDatabase($user);
    }
}
