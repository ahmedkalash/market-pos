@php use App\Services\LocaleService; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ LocaleService::getCurrentLocaleDir() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('invoice.type_' . $type) }} - {{ $invoice->invoice_number ?? $invoice->return_number }}</title>
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

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: {{ LocaleService::getCurrentLocaleDir() === 'rtl' ? 'left' : 'right' }};
        }

        .text-left {
            text-align: {{ LocaleService::getCurrentLocaleDir() === 'rtl' ? 'right' : 'left' }};
        }

        .font-bold {
            font-weight: bold;
        }

        .mb-1 {
            margin-bottom: 5px;
        }

        .mb-2 {
            margin-bottom: 10px;
        }

        .mt-1 {
            margin-top: 5px;
        }

        .mt-2 {
            margin-top: 10px;
        }

        .border-bottom {
            border-bottom: 1px dashed #000;
        }

        .border-top {
            border-top: 1px dashed #000;
        }

        .py-1 {
            padding-top: 5px;
            padding-bottom: 5px;
        }

        .pb-1 {
            padding-bottom: 5px;
        }

        .pt-2 {
            padding-top: 10px;
        }

        .align-bottom {
            vertical-align: bottom;
        }

        .w-full {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 4px 0;
            vertical-align: top;
        }

        .logo {
            max-width: 60mm;
            max-height: 20mm;
            margin: 0 auto 10px auto;
            display: block;
        }

        .totals-table td {
            padding: 2px 0;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
                margin: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="text-center mb-2 pb-1">
    @if($store)
        <div class="font-bold" style="font-size: 18px; margin-bottom: 4px;">{{ $store->name }}</div>
        @if($store->receipt_header)
            <div style="font-size: 11px;">{!! nl2br(e($store->receipt_header)) !!}</div>
        @endif
    @endif
</div>

@yield('content')

<div class="text-center mt-2 border-top pt-2" style="font-size: 11px;">
    <div class="font-bold">{{ __('app.thank_you_visit') }}</div>
    @if($store && $store->receipt_footer)
        <div class="mt-1" style="color: #666;">{!! nl2br(e($store->receipt_footer)) !!}</div>
    @endif
</div>

<script>
    window.addEventListener('load', function() {
        window.print();
    });
</script>
</body>
</html>
