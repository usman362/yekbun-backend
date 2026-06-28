<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; color: #1a1a1a; font-size: 12px; margin: 0; padding: 32px; }
    .row { width: 100%; }
    .row:after { content: ""; display: table; clear: both; }
    .col-left { float: left; width: 50%; }
    .col-right { float: right; width: 50%; text-align: right; }
    h1 { font-size: 26px; margin: 0; color: #d4a017; letter-spacing: 1px; }
    .brand { font-size: 15px; font-weight: bold; }
    .muted { color: #777; }
    .pill { display: inline-block; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: bold; }
    .pill-green { background: #e7f8ee; color: #1a8f4b; }
    .pill-amber { background: #fff4e0; color: #b8770a; }
    .section { margin-top: 22px; }
    .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; color: #999; margin-bottom: 3px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.items th { background: #f5f5f5; text-align: left; padding: 8px 10px; font-size: 11px; color: #555; border-bottom: 1px solid #e5e5e5; }
    table.items td { padding: 8px 10px; border-bottom: 1px solid #efefef; }
    .ta-r { text-align: right; }
    table.totals { width: 45%; float: right; border-collapse: collapse; margin-top: 12px; }
    table.totals td { padding: 5px 10px; }
    table.totals tr.grand td { border-top: 2px solid #1a1a1a; font-weight: bold; font-size: 13px; }
    .foot { margin-top: 50px; text-align: center; color: #999; font-size: 10px; border-top: 1px solid #eee; padding-top: 12px; }
  </style>
</head>
<body>
  <div class="row">
    <div class="col-left">
      <h1>INVOICE</h1>
      <div class="muted" style="margin-top:4px;">{{ $invoice['invoice_id'] }}</div>
    </div>
    <div class="col-right">
      <div class="brand">YekBûn · Zercash</div>
      <div class="muted">Kurd û Kurdî</div>
    </div>
  </div>

  <div class="row section">
    <div class="col-left">
      <div class="label">Billed To</div>
      <div style="font-weight:bold;">{{ $invoice['customer']['name'] ?: 'Customer' }}</div>
      @if($invoice['customer']['email'])<div class="muted">{{ $invoice['customer']['email'] }}</div>@endif
      @if($invoice['customer']['phone'])<div class="muted">{{ $invoice['customer']['phone'] }}</div>@endif
    </div>
    <div class="col-right">
      <div class="label">Details</div>
      <div>Order: <strong>{{ $invoice['order_number'] }}</strong></div>
      <div class="muted">Date: {{ $date }}</div>
      <div class="muted">Payment: {{ ucfirst($invoice['payment_method'] ?: '—') }}</div>
      <div style="margin-top:6px;">
        <span class="pill {{ strtoupper($invoice['status']) === 'COMPLETED' ? 'pill-green' : 'pill-amber' }}">
          {{ strtoupper($invoice['status']) }}
        </span>
      </div>
    </div>
  </div>

  <div class="section">
    <table class="items">
      <thead>
        <tr>
          <th>Item</th>
          <th class="ta-r">Qty</th>
          <th class="ta-r">Zêr</th>
          <th class="ta-r">€</th>
          <th class="ta-r">Line Total</th>
        </tr>
      </thead>
      <tbody>
        @forelse($invoice['items'] as $it)
          <tr>
            <td>{{ $it['name'] ?? 'Item' }}</td>
            <td class="ta-r">{{ $it['qty'] ?? 1 }}</td>
            <td class="ta-r">{{ number_format((float)($it['zer_amount'] ?? 0), 2) }}</td>
            <td class="ta-r">{{ number_format((float)($it['fiat_amount'] ?? 0), 2) }}</td>
            <td class="ta-r">
              @if((float)($it['line_zer'] ?? 0) > 0) ₪ {{ number_format((float)$it['line_zer'], 2) }}
              @else € {{ number_format((float)($it['line_fiat'] ?? 0), 2) }} @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="muted">No items</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="row">
    <table class="totals">
      <tr><td>Subtotal</td><td class="ta-r">{{ number_format((float)$invoice['subtotal'], 2) }}</td></tr>
      @if((float)$invoice['total_zer'] > 0)
        <tr><td>Total (Zêr)</td><td class="ta-r">₪ {{ number_format((float)$invoice['total_zer'], 2) }}</td></tr>
      @endif
      @if((float)$invoice['total_fiat'] > 0)
        <tr><td>Total (€)</td><td class="ta-r">€ {{ number_format((float)$invoice['total_fiat'], 2) }}</td></tr>
      @endif
      @if((float)$invoice['cashback_earned'] > 0)
        <tr><td>Cashback</td><td class="ta-r">₪ {{ number_format((float)$invoice['cashback_earned'], 2) }}</td></tr>
      @endif
      <tr class="grand">
        <td>Total Paid</td>
        <td class="ta-r">
          @if((float)$invoice['total_zer'] > 0) ₪ {{ number_format((float)$invoice['total_zer'], 2) }}
          @else € {{ number_format((float)$invoice['total_fiat'], 2) }} @endif
        </td>
      </tr>
    </table>
  </div>

  <div class="foot">
    Thank you for your purchase · YekBûn Zercash · This is a system-generated invoice.
  </div>
</body>
</html>
