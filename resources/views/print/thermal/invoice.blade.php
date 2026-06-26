@php use App\Enums\InvoiceType; @endphp
@extends('print.layouts.thermal-receipt')

@section('content')
    <div class="border-top border-bottom py-1 mb-2">
        <div><span class="font-bold">{{ __('app.invoice_number') }}:</span> {{ $invoice->invoice_number }}</div>
        <div><span class="font-bold">{{ __('app.created_at') }}:</span> {{ $invoice->created_at->format('Y-m-d H:i') }}</div>
        <div><span class="font-bold">{{ __('app.cashier') }}:</span> {{ $invoice->createdBy?->name ?? 'System' }}</div>

        @if($type === InvoiceType::SaleInvoice->value)
            <div><span class="font-bold">{{ __('app.customer') }}:</span> {{ $invoice->customer?->name }}</div>
            <div><span class="font-bold">{{ __('sale_invoice.payment_method') }}:</span> {{ $invoice->payment_method?->getLabel() }}</div>
        @elseif($type === InvoiceType::PurchaseInvoice->value)
            <div><span class="font-bold">{{ __('vendor.vendor') }}:</span> {{ $invoice->vendor?->name }}</div>
            <div><span class="font-bold">{{ __('purchase_invoice.vendor_invoice_ref') }}:</span> {{ $invoice->vendor_invoice_ref }}</div>
            <div><span class="font-bold">{{ __('purchase_invoice.received_at') }}:</span> {{ $invoice->received_at?->format('Y-m-d') }}</div>
        @endif

    </div>

    <table class="mb-2 w-full">
        <thead class="border-bottom">
            <tr>
                <th class="text-center" style="width: 25%;">{{ __('app.price') }}</th>
                <th class="text-center" style="width: 20%;">{{ __('app.qty') }}</th>
                <th class="text-center" style="width: 25%;">{{ __('app.discount') }}</th>
                <th class="text-right" style="width: 30%;">{{ __('app.total') }}</th>
            </tr>
        </thead>
        <tbody class="border-bottom">
            @foreach($invoice->items as $item)
                <tr>
                    <td colspan="4" style="padding-bottom: 0;">
                        <div class="font-bold" style="font-size: 11px;">
                            {{ $item->variant?->full_qualified_name ?? __('app.unknown_product') }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ number_format($type === InvoiceType::PurchaseInvoice->value ? $item->unit_cost : $item->unit_price, 2) }}</td>
                    <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ (float)$item->quantity }}</td>
                    <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ $item->line_total_discount > 0 ? number_format($item->line_total_discount, 2) : '0.00' }}</td>
                    <td class="text-right py-1 align-bottom" style="font-size: 10px;">{{ number_format($item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($invoice->extraItems && $invoice->extraItems->count() > 0)
        <div class="text-center font-bold py-1" style="font-size: 11px; margin-bottom: 4px;">
            {{ __('app.extra_items') }}
        </div>
        <table class="mb-2 border-bottom w-full" style="padding-bottom: 4px;">
            <tbody>
                @foreach($invoice->extraItems as $extra)
                    <tr>
                        <td>
                            <div class="font-bold">{{ $extra->name }}</div>
                            @if($extra->notes)
                                <div style="color: #666; font-size: 9px;">{{ $extra->notes }}</div>
                            @endif
                        </td>
                        <td class="text-right align-bottom">
                            {{ $extra->signed_amount > 0 ? '+' : '' }}{{ number_format($extra->signed_amount, 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="totals-table py-1 w-full">
        <tr>
            <td class="font-bold">{{ __('app.subtotal') }}</td>
            <td class="text-right">{{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td>{{ __('app.discount') }}</td>
            <td class="text-right"> -{{ number_format($invoice->grand_total_discount, 2) }}</td>
        </tr>
        @if($invoice->extra_items_total != 0)
        <tr>
            <td>{{ __('app.extra_adjustments') }}</td>
            <td class="text-right">{{ $invoice->extra_items_total > 0 ? '+' : '' }}{{ number_format($invoice->extra_items_total, 2) }}</td>
        </tr>
        @endif
        <tr class="border-top font-bold" style="font-size: 14px;">
            <td class="py-1">{{ __('app.total') }}</td>
            <td class="py-1 text-right">{{ number_format($invoice->total_amount, 2) }}</td>
        </tr>
    </table>
@endsection
