<?php

namespace App\Observers;

use App\Enums\Roles;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\NotifyLowStock;
use Illuminate\Support\Facades\Notification;

class ProductVariantObserver
{
    /**
     * Handle the ProductVariant "updated" event.
     */
    public function updated(ProductVariant $productVariant): void
    {
        $this->observeLowStock($productVariant);
    }

    /**
     * Notify relevant managers and admins.
     */
    protected function sendLowStockNotification(ProductVariant $variant): void
    {
        $storeId = $variant->product->store_id;
        $companyId = $variant->product->store->company_id;

        // Find Store Managers of the specific store
        // We use withoutGlobalScopes() because the User model has a global store scope
        // that filters users by the current auth user's store, which would skip company admins.
        $storeManagers = User::query()
            ->withoutGlobalScopes()
            ->where('store_id', $storeId)
            ->whereHas('roles', function ($query) use ($companyId) {
                $query->where('roles.name', Roles::STORE_MANAGER->value)
                    ->where('roles.company_id', $companyId);
            })
            ->get();

        // Find Company Admins of the company
        $companyAdmins = User::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereHas('roles', function ($query) use ($companyId) {
                $query->where('roles.name', Roles::COMPANY_ADMIN->value)
                    ->where('roles.company_id', $companyId);
            })
            ->get();

        $recipients = $storeManagers->concat($companyAdmins)->unique('id');

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NotifyLowStock($variant));
        }
    }

    private function observeLowStock(ProductVariant $productVariant): void
    {
        // Only proceed if quantity or threshold has changed
        if (! $productVariant->wasChanged(['quantity', 'low_stock_threshold'])) {
            return;
        }

        $threshold = $productVariant->low_stock_threshold;

        // Skip if no threshold is set
        if ($threshold === null) {
            // Reset flag if threshold is removed
            if ($productVariant->low_stock_alert_fired) {
                $productVariant->updateQuietly(['low_stock_alert_fired' => false]);
            }

            return;
        }

        // 1. Fire alert if stock is low and alert hasn't been fired yet
        if ($productVariant->isLowStock() && ! $productVariant->low_stock_alert_fired) {
            $this->sendLowStockNotification($productVariant);

            // Update flag quietly to avoid recursive observer loops
            $productVariant->updateQuietly(['low_stock_alert_fired' => true]);
        }
        // 2. Reset flag if stock is replenished above threshold
        elseif (! $productVariant->isLowStock() && $productVariant->low_stock_alert_fired) {
            $productVariant->updateQuietly(['low_stock_alert_fired' => false]);
        }
    }
}
