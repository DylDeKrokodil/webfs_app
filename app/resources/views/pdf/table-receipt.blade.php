<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 4mm;
            size: 85mm 100mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8px;
            line-height: 1.24;
        }

        .receipt {
            width: 77mm;
        }

        .receipt-header {
            border-bottom: 0.4mm solid #7f1d1d;
            padding-bottom: 2mm;
            text-align: center;
        }

        .receipt-logo {
            display: inline-block;
            width: 10mm;
            height: 10mm;
            border: 0.4mm solid #d7b56d;
            background: #7f1d1d;
            color: #fff7d6;
            font-size: 10px;
            font-weight: 800;
            line-height: 9.2mm;
            text-align: center;
        }

        h1 {
            margin: 0.8mm 0 0;
            color: #7f1d1d;
            font-size: 13px;
            line-height: 1;
        }

        .meta {
            width: 100%;
            margin: 2mm 0;
            border-collapse: collapse;
        }

        .meta td {
            padding: 0.4mm 0;
            vertical-align: top;
        }

        .meta td:last-child {
            font-weight: 700;
            text-align: right;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
        }

        .items thead {
            display: table-header-group;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .items th {
            border-bottom: 0.2mm solid #cbd5e1;
            padding: 0 0 1mm;
            color: #475569;
            font-size: 6.6px;
            text-align: right;
            text-transform: uppercase;
        }

        .items th:first-child,
        .items th:nth-child(2) {
            text-align: left;
        }

        .items td {
            border-bottom: 0.2mm solid #e5e7eb;
            padding: 1mm 0;
            vertical-align: middle;
        }

        .thumb {
            width: 8mm;
            height: 8mm;
            border: 0.2mm solid #d8d4ca;
            background: #fff7df;
            color: #7f1d1d;
            font-size: 6.2px;
            font-weight: 700;
            text-align: center;
        }

        .item-name {
            width: 29mm;
            padding-left: 1mm;
            font-weight: 700;
        }

        .item-note {
            margin-top: 0.8mm;
            color: #7f1d1d;
            font-size: 6.6px;
            font-weight: 400;
        }

        .number,
        .money {
            text-align: right;
            white-space: nowrap;
        }

        .totals {
            width: 100%;
            margin-top: 2mm;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .totals td {
            padding: 0.7mm 0;
        }

        .totals td:last-child {
            font-weight: 700;
            text-align: right;
        }

        .grand-total td {
            border-top: 0.4mm solid #7f1d1d;
            padding-top: 1.2mm;
            font-size: 11px;
            font-weight: 800;
        }

        .footer {
            margin-top: 2mm;
            color: #64748b;
            font-size: 6.8px;
            text-align: center;
        }
    </style>
</head>
<body>
    <main class="receipt">
        <header class="receipt-header">
            <div class="receipt-logo">GD</div>
            <h1>De Gouden Draak</h1>
            <div>Restaurantrekening</div>
        </header>

        <table class="meta">
            <tr>
                <td>Tafel</td>
                <td>{{ $receipt['table_code'] }}</td>
            </tr>
            <tr>
                <td>Bestellingen</td>
                <td>{{ implode(', ', $receipt['order_ids']) }}</td>
            </tr>
            <tr>
                <td>Betaald op</td>
                <td>{{ $receipt['paid_at'] ?? now()->format('d-m-Y H:i') }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Afbeelding</th>
                    <th>Gerecht</th>
                    <th>St.</th>
                    <th>Aantal</th>
                    <th>Totaal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receipt['lines'] as $line)
                    <tr>
                        <td>
                            <div class="thumb">{{ $line['display_number'] ?: 'GD' }}</div>
                        </td>
                        <td class="item-name">
                            {{ $line['name'] }}
                            @if (! empty($line['notes']))
                                <div class="item-note">{{ implode(' · ', $line['notes']) }}</div>
                            @endif
                        </td>
                        <td class="money">€ {{ number_format($line['unit_price'], 2, ',', '.') }}</td>
                        <td class="number">{{ $line['quantity'] }}</td>
                        <td class="money">€ {{ number_format($line['line_total'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td>Subtotaal</td>
                <td>€ {{ number_format($receipt['subtotal'], 2, ',', '.') }}</td>
            </tr>
            <tr class="grand-total">
                <td>Totaal</td>
                <td>€ {{ number_format($receipt['total'], 2, ',', '.') }}</td>
            </tr>
        </table>

        <p class="footer">Paginaformaat: 8,5 cm x 10 cm. Bedankt voor uw bezoek.</p>
    </main>
</body>
</html>
