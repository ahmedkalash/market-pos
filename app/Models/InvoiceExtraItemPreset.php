<?php

namespace App\Models;

use App\Enums\ExtraItemActionType;
use App\Enums\InvoiceType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceExtraItemPreset extends Model
{
    use BelongsToCompany, BelongsToStore, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'store_id',
        'name',
        'action_type',
        'amount',
        'is_refundable',
        'invoice_type',
        'notes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_type' => ExtraItemActionType::class,
            'invoice_type' => InvoiceType::class,
            'amount' => 'decimal:2',
            'is_refundable' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    public function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    #[Scope]
    public function forSaleInvoice(Builder $query): Builder
    {
        return $query->where('invoice_type', InvoiceType::SaleInvoice);
    }

    #[Scope]
    public function forSaleReturn(Builder $query): Builder
    {
        return $query->where('invoice_type', InvoiceType::SaleReturn);
    }

    #[Scope]
    public function forPurchaseInvoice(Builder $query): Builder
    {
        return $query->where('invoice_type', InvoiceType::PurchaseInvoice);
    }

    #[Scope]
    public function forPurchaseReturn(Builder $query): Builder
    {
        return $query->where('invoice_type', InvoiceType::PurchaseReturn);
    }
}
