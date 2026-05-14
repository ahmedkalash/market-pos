<?php

namespace App\Models;

use App\Enums\PurchaseReturnStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseReturn extends Model
{
    use BelongsToCompany;
    use BelongsToStore;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'vendor_id',
        'original_invoice_id',
        'return_number',
        'vendor_credit_ref',
        'return_reason',
        'status',
        'total_before_tax',
        'total_tax_amount',
        'total_amount',
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
            'status' => PurchaseReturnStatus::class,
            'returned_at' => 'date',
            'finalized_at' => 'datetime',
            'total_before_tax' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function isFinalized(): bool
    {
        return $this->status === PurchaseReturnStatus::Finalized;
    }

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'original_invoice_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return HasMany<PurchaseReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
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
        return $query->where('status', PurchaseReturnStatus::Finalized);
    }

    #[Scope]
    public function draft(Builder $query): Builder
    {
        return $query->where('status', PurchaseReturnStatus::Draft);
    }
}
