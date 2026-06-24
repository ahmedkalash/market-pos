<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SaleInvoice;
use App\Models\SaleReturnInvoice;
use Illuminate\Http\Request;

class PrintInvoiceController extends Controller
{
    public function __invoke(string $type, int|string $id, Request $request)
    {
        $size = $request->query('size', 'thermal');

        $modelClass = match ($type) {
            InvoiceType::SaleInvoice->value => SaleInvoice::class,
            InvoiceType::SaleReturn->value => SaleReturnInvoice::class,
            InvoiceType::PurchaseInvoice->value => PurchaseInvoice::class,
            InvoiceType::PurchaseReturn->value => PurchaseReturn::class,
            default => abort(404, 'Invalid invoice type'),
        };

        $invoice = $modelClass::with([
            'store',
            'company',
            'items.variant.product',
            'createdBy',
            'extraItems',
        ])->findOrFail($id);

        if (in_array($type, [InvoiceType::SaleInvoice->value, InvoiceType::SaleReturn->value])) {
            $invoice->loadMissing(['customer']);
        } else {
            $invoice->loadMissing(['vendor']);
        }

        if (in_array($type, [InvoiceType::SaleReturn->value, InvoiceType::PurchaseReturn->value])) {
            $invoice->loadMissing(['originalInvoice']);
        }

        $viewName = in_array($type, [InvoiceType::SaleReturn->value, InvoiceType::PurchaseReturn->value])
            ? 'print.thermal.return'
            : 'print.thermal.invoice';

        return view($viewName, [
            'invoice' => $invoice,
            'type' => $type,
            'store' => $invoice->store,
            'company' => $invoice->company,
            'isThermal' => $size === 'thermal',
        ]);
    }
}
