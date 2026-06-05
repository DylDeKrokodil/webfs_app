<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 14mm 12mm;
            size: A4;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.35;
        }

        .header {
            border-bottom: 1.2mm solid #7f1d1d;
            padding-bottom: 5mm;
            text-align: center;
        }

        .logo {
            display: inline-block;
            width: 15mm;
            height: 15mm;
            border: 0.5mm solid #d7b56d;
            background: #7f1d1d;
            color: #fff7d6;
            font-size: 15px;
            font-weight: 800;
            line-height: 14mm;
            text-align: center;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        h1 {
            margin-top: 2mm;
            color: #7f1d1d;
            font-size: 25px;
            line-height: 1.1;
        }

        .meta {
            margin-top: 1.5mm;
            color: #6b7280;
            font-size: 9px;
        }

        .category {
            margin-top: 6mm;
            page-break-inside: avoid;
        }

        .category h2 {
            border-bottom: 0.35mm solid #d7b56d;
            color: #7f1d1d;
            font-size: 15px;
            padding-bottom: 1.4mm;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
        }

        .items tr {
            page-break-inside: avoid;
        }

        .items td {
            border-bottom: 0.2mm solid #e5e7eb;
            padding: 1.7mm 0;
            vertical-align: top;
        }

        .code {
            width: 13mm;
            color: #7f1d1d;
            font-weight: 800;
            white-space: nowrap;
        }

        .name {
            font-weight: 800;
        }

        .description {
            margin-top: 0.7mm;
            color: #6b7280;
            font-size: 8.8px;
        }

        .price {
            width: 20mm;
            font-weight: 800;
            text-align: right;
            white-space: nowrap;
        }

        .offers-page {
            page-break-before: always;
        }

        .offer {
            margin-top: 6mm;
            border: 0.3mm solid #d7b56d;
            padding: 4mm;
            page-break-inside: avoid;
        }

        .offer h3 {
            color: #7f1d1d;
            font-size: 14px;
        }

        .offer-period {
            margin-top: 1mm;
            color: #6b7280;
            font-size: 8.8px;
        }

        .offer-description {
            margin-top: 2mm;
        }

        .offer-items {
            margin-top: 3mm;
            width: 100%;
            border-collapse: collapse;
        }

        .offer-items td {
            border-top: 0.2mm solid #e5e7eb;
            padding: 1.5mm 0;
            vertical-align: top;
        }

        .empty-offers {
            margin-top: 8mm;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">GD</div>
        <h1>Menukaart De Gouden Draak</h1>
        <p class="meta">Gegenereerd op {{ $generatedAt->format('d-m-Y H:i') }}</p>
    </header>

    <main>
        @foreach ($categories as $category)
            <section class="category">
                <h2>{{ $category->name }}</h2>
                <table class="items">
                    <tbody>
                        @foreach ($category->items as $item)
                            <tr>
                                <td class="code">{{ trim(($item->number ?? '').($item->suffix ?? '')) ?: '-' }}</td>
                                <td>
                                    <div class="name">{{ $item->name }}</div>
                                    @if ($item->description)
                                        <div class="description">{{ $item->description }}</div>
                                    @endif
                                </td>
                                <td class="price">€ {{ number_format((float) $item->price, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        <section class="offers-page">
            <header class="header">
                <div class="logo">GD</div>
                <h1>Aanbiedingen</h1>
                <p class="meta">Actuele aanbiedingen bij De Gouden Draak</p>
            </header>

            @forelse ($promotions as $promotion)
                <article class="offer">
                    <h3>{{ $promotion->title }}</h3>
                    <p class="offer-period">
                        Geldig van {{ $promotion->starts_at->format('d-m-Y') }}
                        t/m {{ $promotion->ends_at->format('d-m-Y') }}
                    </p>
                    @if ($promotion->description)
                        <p class="offer-description">{{ $promotion->description }}</p>
                    @endif

                    @if ($promotion->menuItems->isNotEmpty())
                        <table class="offer-items">
                            <tbody>
                                @foreach ($promotion->menuItems as $item)
                                    <tr>
                                        <td class="code">{{ trim(($item->number ?? '').($item->suffix ?? '')) ?: '-' }}</td>
                                        <td>
                                            <div class="name">{{ $item->name }}</div>
                                            <div class="description">{{ $item->category?->name ?? 'Overig' }}</div>
                                        </td>
                                        <td class="price">
                                            @if ((float) $item->pivot->discount_amount > 0)
                                                € {{ number_format((float) $item->pivot->discount_amount, 2, ',', '.') }} korting
                                            @else
                                                Aanbieding
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </article>
            @empty
                <p class="empty-offers">Er zijn momenteel geen actieve aanbiedingen.</p>
            @endforelse
        </section>
    </main>
</body>
</html>
