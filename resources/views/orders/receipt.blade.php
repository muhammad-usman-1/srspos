<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bill #{{ $order->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: 80mm auto; margin: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.35; width: 80mm; padding: 3mm 4mm; color: #000; }
        .center { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .logo { max-width: 60mm; max-height: 22mm; margin-bottom: 3px; }
        .address { font-weight: bold; font-size: 12px; margin: 2px 0 4px; white-space: pre-line; }
        .phone { border: 2px solid #000; font-weight: bold; padding: 3px 4px; margin: 4px 0; font-size: 12px; }
        .title { font-weight: bold; font-size: 13px; margin: 6px 0 3px; }
        .meta { display: flex; justify-content: space-between; font-size: 11.5px; }
        table { width: 100%; border-collapse: collapse; }
        .items { border-top: 2px solid #000; border-bottom: 1px solid #000; margin-top: 4px; }
        .items { table-layout: fixed; }
        .items th, .items td { white-space: nowrap; overflow: hidden; }
        .items th { font-size: 10.5px; text-align: right; padding: 2px 1px; font-weight: bold; }
        .items th.l { text-align: left; }
        .items thead { border-bottom: 1px solid #000; }
        .items td { padding: 1px 1px; font-size: 11.5px; text-align: right; vertical-align: top; }
        .items td.l { text-align: left; }
        .items .name { font-weight: bold; white-space: normal; }
        .items tr.sep td { border-bottom: 1px dashed #000; padding-bottom: 3px; }
        .totals td { font-size: 14px; font-weight: bold; padding: 2px 0; }
        .totals td.s { font-size: 12px; }
        .box { border: 1px dashed #000; margin: 5px 0; }
        .box td { padding: 3px 4px; font-weight: bold; font-size: 13px; }
        .box td + td { border-left: 1px solid #000; text-align: right; }
        .box .dark { background: #000; color: #fff; }
        .policy { border: 2px solid #000; padding: 4px; font-size: 10px; text-align: center; margin: 6px 0; white-space: pre-line; }
        .thanks { font-family: 'Times New Roman', serif; font-weight: bold; font-size: 15px; text-align: center; border: 2px solid #000; padding: 4px; margin: 5px 0; }
        .credit { font-size: 9px; text-align: center; }
        @media screen { body { margin: 10px auto; border: 1px solid #ddd; } }
        @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
@php
    $n = fn($v) => number_format((float) $v, 2);
    $subtotal = $order->subtotal();
    $total = $order->total();
    $payment = $order->payments->first();
    $paid = $order->receivedAmount();
    $tendered = $payment && $payment->tendered !== null ? (float) $payment->tendered : $paid;
    $balance = $tendered - $total;        // change handed back
    $due = max($total - $paid, 0);        // still owed by the customer
    $totalQty = $order->items->sum('quantity');
    $marketTotal = $order->items->sum(fn($i) => ($i->mkt_price ?? ($i->quantity ? $i->price / $i->quantity : 0)) * $i->quantity);
    $gstRate = (float) $order->tax_rate;
    $address = config('settings.store_address');
    $phone = config('settings.store_phone');
    $policy = config('settings.receipt_policy');
    $footer = config('settings.receipt_footer') ?: 'THANKS FOR YOUR VISIT';
    $credit = config('settings.receipt_credit');
    $methodName = $payment ? $payment->methodLabel() : 'Cash';
@endphp
    <div class="center">
        <img class="logo" src="{{ app_logo_url() }}" alt="">
        @if(!$address)
            <div class="b" style="font-size:15px">{{ config('app.name') }}</div>
        @endif
        @if($address)<div class="address">{{ $address }}</div>@endif
        @if($phone)<div class="phone">Ph: {{ $phone }}</div>@endif
        <div class="title">{{ strtoupper(config('settings.receipt_title') ?: 'Original Sales Invoice') }}</div>
    </div>
    <div class="meta"><span>Date &amp; Time: {{ $order->created_at->format('d-M-y') }}</span><span>{{ $order->created_at->format('h:i:s A') }}</span></div>
    <div class="meta"><span>Cashier: {{ $order->user?->getFullname() ?? '-' }}</span><span>Bill No: <b>{{ $order->id }}</b></span></div>
    @if($order->customer)
        <div class="meta"><span>Customer: {{ $order->getCustomerName() }}</span></div>
    @endif

    <table class="items">
        <colgroup>
            <col style="width:35%"><col style="width:13%"><col style="width:13%"><col style="width:19%"><col style="width:20%">
        </colgroup>
        <thead>
        <tr>
            <th class="l" colspan="3">No. &nbsp;Item Name / Barcode</th>
            <th>MKT Price</th>
            <th>Amount</th>
        </tr>
        <tr>
            <th class="l"></th>
            <th>GST %</th>
            <th>Qty</th>
            <th>Price</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        @foreach($order->items as $i => $item)
            @php $unit = $item->quantity ? $item->price / $item->quantity : 0; @endphp
            <tr>
                <td class="l name" colspan="3">{{ $i + 1 }}. {{ $item->product->name ?? 'Item' }}</td>
                <td>{{ $item->mkt_price !== null ? $n($item->mkt_price) : '' }}</td>
                <td class="b">{{ $n($item->price) }}</td>
            </tr>
            <tr class="sep">
                <td class="l">{{ $item->product->barcode ?? '' }}</td>
                <td>{{ $gstRate > 0 ? rtrim(rtrim($n($gstRate), '0'), '.') . '%' : '%' }}</td>
                <td>{{ $n($item->quantity) }}</td>
                <td>{{ $n($unit) }}</td>
                <td></td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top:4px">
        @if($order->discount > 0)
            <tr><td class="s">Discount</td><td class="r s">- {{ $n($order->discount) }}</td></tr>
        @endif
        <tr><td>Grand Total</td><td class="r">{{ $n($total) }}</td></tr>
        <tr><td>{{ $methodName }} Paid</td><td class="r">{{ $n($tendered) }}</td></tr>
        @if($due > 0)
            <tr><td>Amount Due</td><td class="r">{{ $n($due) }}</td></tr>
        @else
            <tr><td>Balance</td><td class="r">{{ $n(max($balance, 0)) }}</td></tr>
        @endif
    </table>

    <table class="box">
        <tr><td>Market Total</td><td class="dark">{{ $n($marketTotal) }}</td></tr>
        <tr><td>GST This Bill</td><td>{{ $n($order->tax_amount) }}</td></tr>
    </table>

    @if($policy)<div class="policy">{{ $policy }}</div>@endif
    <div class="thanks">{{ strtoupper($footer) }}</div>
    @if($credit)<div class="credit">{{ $credit }}</div>@endif

@if(request()->boolean('print'))
    <script>
        window.onload = function () {
            window.focus();
            window.print();
        };
    </script>
@endif
</body>
</html>
