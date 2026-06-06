<?php

namespace App\Models;

use App\Enums\SaleReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturnInvoice extends Model
{
    use BelongsToCompany, BelongsToStore, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'customer_id',
        'original_invoice_id',
        'return_number',
        'status',
        'return_reason',
        'items_refund_total',
        'extra_items_total',
        'total_refund_amount',
        'notes',
        'returned_at',
        'finalized_at',
        'finalized_by',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleReturnStatus::class,
            'returned_at' => 'date',
            'finalized_at' => 'datetime',
            'items_refund_total' => 'decimal:2',
            'extra_items_total' => 'decimal:2',
            'total_refund_amount' => 'decimal:2',
        ];
    }

    public function isFinalized(): bool
    {
        return $this->status === SaleReturnStatus::Finalized;
    }

    public function isDraft(): bool
    {
        return $this->status === SaleReturnStatus::Draft;
    }

    /**
     * @return BelongsTo<SaleInvoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(SaleInvoice::class, 'original_invoice_id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SaleReturnInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnInvoiceItem::class);
    }

    /**
     * @return HasMany<SaleReturnExtraItem, $this>
     */
    public function extraItems(): HasMany
    {
        return $this->hasMany(SaleReturnExtraItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withoutGlobalScopes();
    }

    #[Scope]
    public function finalized(Builder $query): Builder
    {
        return $query->where('status', SaleReturnStatus::Finalized);
    }

    #[Scope]
    public function draft(Builder $query): Builder
    {
        return $query->where('status', SaleReturnStatus::Draft);
    }

    /**
     * Calculate the total sum of extra items, factoring in their addition/deduction types.
     */
    public function calculateExtraItemsTotal(): float
    {
        $this->loadMissing('extraItems');

        return (float) $this->extraItems->sum('signed_amount');
    }
}
