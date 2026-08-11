<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Nota {{ $sale->document_number }}</title>
    {{--
        AC-14/R13: TIDAK ADA baris atau perhitungan PPN di mana pun pada
        halaman ini — bukan disembunyikan lewat CSS/kondisi, field itu
        memang tidak pernah ditulis.
    --}}
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 12px; width: 320px; margin: 16px auto; color: #000; }
        h1 { font-size: 14px; text-align: center; margin-bottom: 4px; }
        .center { text-align: center; }
        .row { display: flex; justify-content: space-between; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0; }
        td { padding: 2px 0; vertical-align: top; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .total-row { font-weight: bold; }
        .no-print { margin-top: 12px; }
        @media print {
            .no-print { display: none; }
        }
        .watermark-salinan {
            text-align: center;
            font-weight: bold;
            letter-spacing: 2px;
            border: 2px solid #000;
            padding: 2px 0;
            margin-bottom: 6px;
        }
        .receipt-logo {
            display: block;
            width: 220px;
            height: auto;
            margin: 0 auto 6px auto;
        }
    </style>
</head>
<body>
    {{-- T4.11 asli (UT17: "logo terbaca") — sebelumnya nota TIDAK menampilkan
         logo sama sekali. Varian 1-Color-Positive (hitam solid, BUKAN cyan
         #00B4D4 dari HK-LOGO-01) — satu-satunya warna yang dijamin tercetak
         benar pada printer thermal 58/80mm hitam-putih. --}}
    <img src="{{ asset('images/brand/HK-LOGO-04-1Color-Positive.svg') }}"
         alt="HAEN KOMPUTER" class="receipt-logo">

    {{-- T4.11/UT14: cetak ulang WAJIB bertanda "SALINAN" — ditentukan dari
         riwayat audit log (lihat docblock ShowSaleReceiptController), bukan
         dari field pada `$sale` sendiri. --}}
    @if ($isReprint ?? false)
        <p class="watermark-salinan">*** SALINAN ***</p>
    @endif

    <h1>{{ $sale->branch->name }}</h1>
    @if ($sale->branch->address)
        <p class="center">{{ $sale->branch->address }}</p>
    @endif

    <div class="divider"></div>

    <div class="row"><span>No. Nota</span><span>{{ $sale->document_number }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ ($sale->finalized_at ?? $sale->created_at)->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $sale->cashierShift->cashier->name ?? '-' }}</span></div>
    <div class="row"><span>Pelanggan</span><span>{{ $sale->partner->name ?? 'Walk-in' }}</span></div>

    @if ($sale->state->value === 'void')
        <div class="divider"></div>
        <p class="center"><strong>*** DIBATALKAN (VOID) ***</strong></p>
    @endif

    <div class="divider"></div>

    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2">{{ $item->product->name ?? $item->service->name }}</td>
            </tr>
            <tr>
                <td>{{ rtrim(rtrim((string) $item->quantity, '0'), '.') }} x {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td style="text-align: right">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="divider"></div>

    <div class="row"><span>Subtotal</span><span>Rp{{ number_format((float) $sale->subtotal, 0, ',', '.') }}</span></div>
    @if ((float) $sale->discount_amount > 0)
        <div class="row"><span>Diskon</span><span>-Rp{{ number_format((float) $sale->discount_amount, 0, ',', '.') }}</span></div>
    @endif
    <div class="row total-row"><span>TOTAL</span><span>Rp{{ number_format((float) $sale->total_amount, 0, ',', '.') }}</span></div>

    <div class="divider"></div>

    @foreach ($sale->payments as $payment)
        <div class="row"><span>{{ $payment->method->label() }}</span><span>Rp{{ number_format((float) $payment->amount, 0, ',', '.') }}</span></div>
    @endforeach

    @if ((float) $sale->balance_due > 0)
        <div class="row total-row"><span>Sisa (DP)</span><span>Rp{{ number_format((float) $sale->balance_due, 0, ',', '.') }}</span></div>
    @endif

    <div class="divider"></div>
    <p class="center">Terima kasih atas kunjungan Anda.</p>

    <div class="no-print center">
        <button onclick="window.print()">Cetak</button>
    </div>
</body>
</html>
