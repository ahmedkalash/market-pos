@php use App\Enums\InvoiceType; @endphp
@extends('print.layouts.thermal-receipt')

@section('content')
    <div class="border-top border-bottom py-1 mb-2">
        <div><span class="font-bold">{{ __('app.return_number') }}:</span> {{ $invoice->return_number }}</div>
        <div>
            <span class="font-bold">{{ __('app.original_invoice') }}:</span> {{ $invoice->originalInvoice?->invoice_number ?? 'N/A' }}
        </div>
        <div><span class="font-bold">{{ __('app.created_at') }}:</span> {{ $invoice->created_at->format('Y-m-d H:i') }}
        </div>
        <div><span class="font-bold">{{ __('app.cashier') }}:</span> {{ $invoice->createdBy?->name ?? 'System' }}</div>

        @if($type === InvoiceType::SaleReturn->value && $invoice->customer_id)
            <div><span class="font-bold">{{ __('app.customer') }}:</span> {{ $invoice->customer->name }}</div>
        @elseif($type === InvoiceType::PurchaseReturn->value && $invoice->vendor_id)
            <div><span class="font-bold">{{ __('vendor.vendor') }}:</span> {{ $invoice->vendor->name }}</div>
        @endif

        @if($invoice->return_reason)
            <div class="mt-1">
                <span class="font-bold">{{ __('app.return_reason') }}:</span>
                <span>{{ $invoice->return_reason }}</span>
            </div>
        @endif
    </div>

    <table class="mb-2 w-full">
        <thead class="border-bottom">
            <tr>
                <th class="text-center" style="width: 30%;">{{ __('app.price') }}</th>
                <th class="text-center" style="width: 30%;">{{ __('app.qty') }}</th>
                <th class="text-right" style="width: 40%;">{{ __('app.total') }}</th>
            </tr>
        </thead>
        <tbody class="border-bottom">
        @foreach($invoice->items as $item)
            @php
                $total = $type === InvoiceType::SaleReturn->value ? $item->item_refund_total : $item->line_total;
            @endphp
            <tr>
                <td colspan="3" style="padding-bottom: 0;">
                    <div class="font-bold" style="font-size: 11px;">
                        {{ $item->variant?->full_qualified_name }}
                    </div>
                </td>
            </tr>
            <tr>
                <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ number_format($item->effective_unit_refund, 2) }}</td>
                <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ (float)$item->quantity }}</td>
                <td class="text-right py-1 align-bottom" style="font-size: 10px;">{{ number_format($total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if($invoice->extraItems && $invoice->extraItems->count() > 0)
        <div class="text-center font-bold py-1"
             style="font-size: 11px; border-bottom: 1px dashed #000; margin-bottom: 4px;">
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
            <td class="text-right">{{ number_format($type === InvoiceType::SaleReturn->value ? $invoice->items_refund_total : $invoice->items->sum('line_total'), 2) }}</td>
        </tr>
        @if($invoice->extra_items_total != 0)
            <tr>
                <td>{{ __('app.extra_adjustments') }}</td>
                <td class="text-right">{{ $invoice->extra_items_total > 0 ? '+' : '' }}{{ number_format($invoice->extra_items_total, 2) }}</td>
            </tr>
        @endif
        <tr class="border-top font-bold" style="font-size: 14px;">
            <td class="py-1">{{ __('app.total') }}</td>
            <td class="py-1 text-right">{{ number_format($type === InvoiceType::SaleReturn->value ? $invoice->total_refund_amount : $invoice->total_amount, 2) }}</td>
        </tr>
    </table>
@endsection
