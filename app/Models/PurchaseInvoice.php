<?php

namespace App\Models;

use App\Enums\InvoiceReturnStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use BelongsToCompany, BelongsToStore;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'vendor_id',
        'invoice_number',
        'vendor_invoice_ref',
        'total_before_tax',
        'total_tax_amount',
        'total_amount',
        'status',
        'return_status',
        'notes',
        'received_at',
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
            'status' => PurchaseInvoiceStatus::class,
            'return_status' => InvoiceReturnStatus::class,
            'total_before_tax' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'received_at' => 'date',
            'finalized_at' => 'datetime',
        ];
    }

    public function isFinalized(): bool
    {
        return $this->status === PurchaseInvoiceStatus::Finalized;
    }

    public function isDraft(): bool
    {
        return $this->status === PurchaseInvoiceStatus::Draft;
    }

    public function isFullyReturned(): bool
    {
        return $this->return_status === InvoiceReturnStatus::FullyReturned;
    }

    public function isPartiallyReturned(): bool
    {
        return $this->return_status === InvoiceReturnStatus::PartiallyReturned;
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
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

    /**
     * @return HasMany<PurchaseReturn, $this>
     */
    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class, 'original_invoice_id');
    }

    /**
     * @return HasMany<PurchaseInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    #[Scope]
    public function finalized(Builder $query): Builder
    {
        return $query->where('status', PurchaseInvoiceStatus::Finalized);
    }

    #[Scope]
    public function draft(Builder $query): Builder
    {
        return $query->where('status', PurchaseInvoiceStatus::Draft);
    }

    #[Scope]
    public function returnable(Builder $query): Builder
    {
        return $query->finalized()
            ->where('return_status', '!=', InvoiceReturnStatus::FullyReturned);
    }

    #[Scope]
    public function fullyReturned(Builder $query): Builder
    {
        return $query->where('return_status', InvoiceReturnStatus::FullyReturned);
    }

    #[Scope]
    public function partiallyReturned(Builder $query): Builder
    {
        return $query->where('return_status', InvoiceReturnStatus::PartiallyReturned);
    }

    #[Scope]
    public function forVendor(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId);
    }
}
