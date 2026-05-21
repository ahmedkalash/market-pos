<?php

namespace App\Models;

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
        'invoice_number',
        'status',
        'return_status',
        'payment_method',
        'total_before_tax',
        'total_tax_amount',
        'total_amount',
        'notes',
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
            'status' => SaleInvoiceStatus::class,
            'return_status' => SaleInvoiceReturnStatus::class,
            'payment_method' => PaymentMethod::class,
            'total_before_tax' => 'decimal:2',
            'total_tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by')->withoutGlobalScopes();
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
}
