<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('invoice.type_' . $type) }} - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 10px;
            width: 80mm; /* Standard 80mm thermal receipt */
            margin-left: auto;
            margin-right: auto;
            box-sizing: border-box;
        }
        .text-center { text-align: center; }
        .text-right { text-align: {{ app()->getLocale() == 'ar' ? 'left' : 'right' }}; }
        .text-left { text-align: {{ app()->getLocale() == 'ar' ? 'right' : 'left' }}; }
        .font-bold { font-weight: bold; }
        .mb-1 { margin-bottom: 5px; }
        .mb-2 { margin-bottom: 10px; }
        .mt-1 { margin-top: 5px; }
        .mt-2 { margin-top: 10px; }
        .border-bottom { border-bottom: 1px dashed #000; }
        .border-top { border-top: 1px dashed #000; }
        .py-1 { padding-top: 5px; padding-bottom: 5px; }
        .w-full { width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 0; vertical-align: top; }
        .logo { max-width: 60mm; max-height: 20mm; margin: 0 auto 10px auto; display: block; }
        
        .totals-table td { padding: 2px 0; }
        
        @media print {
            .no-print { display: none; }
            body { padding: 0; margin: 0; width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
    
    <div class="text-center mb-2 border-bottom pb-1">
        @if($store)
            <div class="font-bold" style="font-size: 14px;">{{ $store->name }}</div>
            @if($store->receipt_header)
                <div style="font-size: 11px; margin-top: 2px;">{!! nl2br(e($store->receipt_header)) !!}</div>
            @endif
        @elseif($company)
            <div class="font-bold" style="font-size: 14px;">{{ $company->name }}</div>
            @if($company->receipt_header)
                <div style="font-size: 11px; margin-top: 2px;">{!! nl2br(e($company->receipt_header)) !!}</div>
            @endif
        @endif
    </div>

    <div class="border-top border-bottom py-1 mb-2">
        <div><span class="font-bold">{{ __('app.invoice_number') }}:</span> {{ $invoice->invoice_number }}</div>
        <div><span class="font-bold">{{ __('app.created_at') }}:</span> {{ $invoice->created_at->format('Y-m-d H:i') }}</div>
        <div><span class="font-bold">{{ __('app.cashier') }}:</span> {{ $invoice->createdBy?->name ?? 'System' }}</div>
        
        @if(in_array($type, ['sale', 'sale_return']) && $invoice->customer_id)
            <div><span class="font-bold">{{ __('app.customer') }}:</span> {{ $invoice->customer->name }}</div>
        @endif
        @if(in_array($type, ['purchase', 'purchase_return']) && $invoice->vendor_id)
            <div><span class="font-bold">{{ __('vendor.vendor') }}:</span> {{ $invoice->vendor->name }}</div>
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
                            {{ $item->variant?->product?->name ?? 'Unknown' }}
                            @if($item->variant && $item->variant->name)
                                - {{ $item->variant->name }}
                            @endif
                        </div>
                    </td>
                </tr>
                <tr>
                    <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ number_format($item->unit_price ?? $item->unit_cost, 2) }}</td>
                    <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ (float)$item->quantity }}</td>
                    <td class="text-center py-1 align-bottom" style="font-size: 10px;">{{ $item->line_total_discount > 0 ? number_format($item->line_total_discount, 2) : '0.00' }}</td>
                    <td class="text-right py-1 align-bottom" style="font-size: 10px;">{{ number_format($item->line_total ?? ($item->subtotal - $item->line_total_discount), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($invoice->extraItems && $invoice->extraItems->count() > 0)
        <div class="text-center font-bold py-1" style="font-size: 11px; border-bottom: 1px dashed #000; margin-bottom: 4px;">
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
        @if($invoice->discount_amount > 0 || $invoice->global_discount_amount > 0)
        <tr>
            <td>{{ __('app.discount') }}</td>
            <td class="text-right">-{{ number_format($invoice->grand_total_discount, 2) }}</td>
        </tr>
        @endif
        @if($invoice->extra_items_total > 0 || $invoice->extra_items_total < 0)
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

    <div class="text-center mt-2 border-top pt-2" style="font-size: 11px;">
        <div class="font-bold">{{ __('app.thank_you_visit') }}</div>
        @if($store && $store->receipt_footer)
            <div class="mt-1" style="color: #666;">{!! nl2br(e($store->receipt_footer)) !!}</div>
        @elseif($company && $company->receipt_footer)
            <div class="mt-1" style="color: #666;">{!! nl2br(e($company->receipt_footer)) !!}</div>
        @endif
    </div>

</body>
</html>
