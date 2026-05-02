<?php

namespace App\Notifications;

use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NotifyLowStock extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ProductVariant $variant) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification for Filament.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->title(__('inventory.low_stock_alert_title'))
            ->body(__('inventory.low_stock_alert_body', [
                'product' => $this->variant->name_ar.' / '.$this->variant->name_en,
                'qty' => number_format($this->variant->quantity, 2),
                'threshold' => number_format($this->variant->low_stock_threshold, 2),
            ]))
            ->danger()
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([
                Action::make('view_product')
                    ->label(__('inventory.view_product'))
                    ->url(fn () => EditProduct::getUrl(['record' => $this->variant->product_id])),
            ])
            ->getDatabaseMessage();
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'variant_id' => $this->variant->id,
            'quantity' => $this->variant->quantity,
            'threshold' => $this->variant->low_stock_threshold,
        ];
    }
}
