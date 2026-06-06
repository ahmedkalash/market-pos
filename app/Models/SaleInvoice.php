<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\SaleInvoiceReturnStatus;
use App\Enums\SaleInvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleInvoice extends Model
{
    use BelongsToCompany, BelongsToStore, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'customer_id',
        'invoice_number',
        'status',
        'return_status',
        'payment_method',
        'subtotal',
        'total_before_tax',
        'total_tax_amount',
        'total_amount',
        'discount_type',
        'discount_amount',
        'global_discount_amount',
        'grand_total_discount',
        'notes',
        'finalized_at',
        'finalized_by',
        'shipping_destination_id',
        'shipping_cost',
        'shipping_address',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleInvoiceStatus::class,
            'return_status' => SaleInvoiceReturnStatus::class,
            'payment_method' => PaymentMethod::class,
            'discount_type' => DiscountType::class,
            'subtotal' => 'decimal:2',
            'total_before_tax' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'global_discount_amount' => 'decimal:2',
            'grand_total_discount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'finalized_at' => 'datetime',
        ];
    }

    public function isFinalized(): bool
    {
        return $this->status === SaleInvoiceStatus::Finalized;
    }

    public function isDraft(): bool
    {
        return $this->status === SaleInvoiceStatus::Draft;
    }

    public function isFullyReturned(): bool
    {
        return $this->return_status === SaleInvoiceReturnStatus::FullyReturned;
    }

    public function isPartiallyReturned(): bool
    {
        return $this->return_status === SaleInvoiceReturnStatus::PartiallyReturned;
    }

    public function isFullyOrPartiallyReturned(): bool
    {
        return $this->isFullyReturned() || $this->isPartiallyReturned();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<ShippingDestination, $this>
     */
    public function shippingDestination(): BelongsTo
    {
        return $this->belongsTo(ShippingDestination::class);
    }

    /**
     * @return HasMany<SaleInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleInvoiceItem::class);
    }

    #[Scope]
    public function finalized(Builder $query): Builder
    {
        return $query->where('status', SaleInvoiceStatus::Finalized);
    }

    #[Scope]
    public function draft(Builder $query): Builder
    {
        return $query->where('status', SaleInvoiceStatus::Draft);
    }

    #[Scope]
    public function returnable(Builder $query): Builder
    {
        return $query->finalized()
            ->where('return_status', '!=', SaleInvoiceReturnStatus::FullyReturned);
    }

    #[Scope]
    public function fullyReturned(Builder $query): Builder
    {
        return $query->where('return_status', SaleInvoiceReturnStatus::FullyReturned);
    }

    #[Scope]
    public function partiallyReturned(Builder $query): Builder
    {
        return $query->where('return_status', SaleInvoiceReturnStatus::PartiallyReturned);
    }

    #[Scope]
    public function hasNotes(Builder $query): Builder
    {
        return $query->whereNotNull('notes')->where('notes', '!=', '');
    }

    #[Scope]
    public function withoutNotes(Builder $query): Builder
    {
        return $query->whereNull('notes')->orWhere('notes', '');
    }

    /**
     * @return HasMany<SaleReturnInvoice, $this>
     */
    public function saleReturns(): HasMany
    {
        return $this->hasMany(SaleReturnInvoice::class, 'original_invoice_id');
    }

    public function subtotalsAfterItemDiscountSum(): float
    {
        $this->loadMissing('items');

        $subtotalsAfterItemDiscountSum = 0.0;
        foreach ($this->items as $item) {
            $subtotalsAfterItemDiscountSum += $item->subtotalAfterItemDiscount();
        }

        return $subtotalsAfterItemDiscountSum;
    }
}
