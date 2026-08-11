<?php

declare(strict_types=1);

namespace App\Presentation\Pos\Livewire;

use App\Application\Actions\FinalizeSaleAction;
use App\Application\Services\NodeConnectivityService;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Exceptions\StockDocumentValidationException;
use App\Domain\Sales\Exceptions\SaleValidationException;
use App\Domain\Shared\Enums\DocumentState;
use App\Domain\Shared\Enums\PartnerType;
use App\Infrastructure\Persistence\Models\CashierShift;
use App\Infrastructure\Persistence\Models\Partner;
use App\Infrastructure\Persistence\Models\Product;
use App\Infrastructure\Persistence\Models\Sale;
use App\Infrastructure\Persistence\Models\Service;
use App\Infrastructure\Persistence\Support\BranchContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Terminal POS (T4.4) — halaman Livewire mandiri DI LUAR panel Filament
 * (HS-ARCH-RIGHTCLICK-v1.1 §2.2, sudah diantisipasi di docblock
 * `AdminPanelProvider`/`filament-theme.css` sejak T1.10). Dirutekan
 * langsung lewat `routes/web.php` (`/pos`), BUKAN `discoverResources`
 * Filament — kebutuhan kecepatan/layar sentuh berbeda dari pola CRUD.
 *
 * POS-05: produk berstok nol TETAP tampil dengan `qty_on_hand` (badge
 * "HABIS" dirender di view, bukan disembunyikan di sini) — `products()`
 * tidak pernah memfilter berdasarkan stok.
 *
 * POS-06: `products()`/`services()` HANYA men-select kolom yang aman
 * dikirim ke Kasir (`selling_price`, BUKAN kolom biaya apa pun) — `Product`
 * sendiri tidak punya kolom harga beli (T2.5, R2), dan query di sini tidak
 * pernah JOIN ke `stock_batches`/`stock_mutations` (satu-satunya tempat
 * `unit_cost` hidup) — disaring di lapisan query (P6), bukan di tampilan.
 *
 * R8/AC-13: checkout HANYA menyentuh database lokal cabang ini secara
 * transaksional — tidak ada panggilan sinkron ke HQ atau layanan eksternal
 * di jalur ini sama sekali, sehingga terputusnya internet eksternal tidak
 * pernah bisa memblokir transaksi. Banner konektivitas (`NodeConnectivityService`,
 * sama layanan dipakai halaman login T1.11) murni informatif (U8) — TIDAK
 * PERNAH menonaktifkan tombol checkout.
 *
 * PV7: tidak ada transisi/animasi di view (`resources/css/pos-theme.css`
 * menegakkannya lewat CSS, bukan konvensi Blade semata).
 *
 * T4.5/UT1 (menutup gap — `HS-TASKS-RIGHTCLICK-v1.1` tidak menspesifikasikan
 * mapping F1-F12 persis, dan `HS-UI-RIGHTCLICK-v1.1` yang seharusnya
 * memuatnya tidak ada di repo; mapping berikut SELF-DERIVED, mudah diganti
 * bila dokumen UI ditemukan): F1=fokus pencarian, F2=fokus qty baris
 * terakhir, F3=`checkout()`, F4=`clearCart()`, Esc=kosongkan kotak
 * pencarian, Enter di pencarian=`addFirstMatch()`. Diimplementasikan via
 * Alpine.js (dibundel Livewire 3 lewat `@livewireScripts`) di root elemen
 * `resources/views/pos/terminal.blade.php` — BUKAN di `layouts.pos.blade.php`,
 * karena `$wire` hanya resolve di dalam subtree komponen Livewire.
 *
 * T4.9/UT5 (menutup gap yang sengaja ditunda T3.7 — lihat docblock
 * `SerialNumberValidationService`): produk `is_serialized` TIDAK PERNAH
 * digabung otomatis ke baris keranjang yang sudah ada (`addLine()`) — serial
 * diketik per baris lewat `serial_numbers_input` (dipisah baris baru/koma),
 * di-parse `parseSerialNumbers()` saat checkout, lalu divalidasi WAJIB oleh
 * `FinalizeSaleAction`/`SerialNumberValidationService` sebelum stok
 * disentuh. Kegagalan validasi (`StockDocumentValidationException`)
 * membatalkan transaksi database (rollback) sama seperti stok kurang —
 * pembayaran diblokir, keranjang tetap utuh untuk diperbaiki.
 */
#[Layout('layouts.pos')]
#[Title('POS — RIGHTCLICK')]
class PosTerminal extends Component
{
    public string $search = '';

    /** @var array<int, array{key: string, type: string, id: string, name: string, quantity: string, unit_price: string, is_serialized: bool, serial_numbers_input: string}> */
    public array $cart = [];

    public ?string $partnerId = null;

    public string $discountAmount = '0';

    /** @var array<int, array{method: string, amount: string, reference_no: string}> */
    public array $payments = [
        ['method' => 'cash', 'amount' => '', 'reference_no' => ''],
    ];

    public ?CashierShift $activeShift = null;

    public string $openingCashInput = '0';

    public ?string $shiftError = null;

    public ?string $checkoutError = null;

    public ?string $successMessage = null;

    public ?string $pendingApprovalMessage = null;

    public ?string $lastFinalizedSaleId = null;

    public function mount(): void
    {
        abort_unless(Auth::user()?->can('create_sale') ?? false, 403);

        $this->loadActiveShift();
    }

    public function isConnectedToHq(): bool
    {
        return app(NodeConnectivityService::class)->isConnectedToHq();
    }

    public function openShift(): void
    {
        $this->shiftError = null;

        if (! (Auth::user()?->can('create', CashierShift::class) ?? false)) {
            $this->shiftError = 'Anda sudah memiliki shift terbuka, atau tidak berwenang membuka shift baru.';

            return;
        }

        CashierShift::create([
            'cashier_id' => Auth::id(),
            'opening_cash' => $this->openingCashInput !== '' ? $this->openingCashInput : '0',
        ]);

        $this->openingCashInput = '0';
        $this->loadActiveShift();
    }

    public function addProduct(string $productId): void
    {
        $product = Product::query()->findOrFail($productId, ['id', 'name', 'selling_price', 'is_serialized']);
        $this->addLine('product', (string) $product->id, (string) $product->name, (string) $product->selling_price, $product->is_serialized);
    }

    public function addService(string $serviceId): void
    {
        $service = Service::query()->findOrFail($serviceId, ['id', 'name', 'price']);
        $this->addLine('service', (string) $service->id, (string) $service->name, (string) $service->price);
    }

    public function removeLine(string $key): void
    {
        $this->cart = array_values(array_filter($this->cart, fn (array $line): bool => $line['key'] !== $key));
    }

    /**
     * Pintasan F4 (T4.5/UT1) — kosongkan keranjang saja, bukan seluruh
     * dokumen (partner/diskon/pembayaran tetap, beda dari `resetCart()`
     * yang dipanggil setelah checkout berhasil).
     */
    public function clearCart(): void
    {
        $this->cart = [];
    }

    /**
     * Pintasan Enter di kotak pencarian (T4.5/UT1) — tambah hasil pertama
     * (produk diprioritaskan, jasa sebagai fallback), sama urutan tampil
     * di katalog. Tanpa hasil sama sekali, tidak melakukan apa pun.
     */
    public function addFirstMatch(): void
    {
        $product = $this->products()->first();

        if ($product !== null) {
            $this->addProduct((string) $product->id);

            return;
        }

        $service = $this->services()->first();

        if ($service !== null) {
            $this->addService((string) $service->id);
        }
    }

    /**
     * @return Collection<int, Product>
     */
    public function products(): Collection
    {
        $branchId = app(BranchContext::class)->current();

        $products = Product::query()
            ->where('is_active', true)
            ->when($this->search !== '', fn ($query) => $query->where(
                fn ($sub) => $sub->where('name', 'ilike', "%{$this->search}%")
                    ->orWhere('sku', 'ilike', "%{$this->search}%"),
            ))
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'sku', 'name', 'selling_price']);

        $balances = DB::table('stock_balances')
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $products->pluck('id'))
            ->pluck('qty_on_hand', 'product_id');

        foreach ($products as $product) {
            $product->setAttribute('qty_on_hand', $balances[$product->id] ?? '0.0000');
        }

        return $products;
    }

    /**
     * @return Collection<int, Service>
     */
    public function services(): Collection
    {
        return Service::query()
            ->where('is_active', true)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'ilike', "%{$this->search}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'price']);
    }

    /**
     * @return Collection<int, Partner>
     */
    public function partners(): Collection
    {
        return Partner::query()
            ->whereIn('partner_type', [PartnerType::Customer->value, PartnerType::Both->value])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function subtotal(): string
    {
        $total = '0';

        foreach ($this->cart as $line) {
            $total = bcadd($total, bcmul($line['quantity'], $line['unit_price'], 2), 2);
        }

        return $total;
    }

    public function total(): string
    {
        $discount = $this->discountAmount !== '' ? $this->discountAmount : '0';

        return bcsub($this->subtotal(), $discount, 2);
    }

    public function paymentsTotal(): string
    {
        $total = '0';

        foreach ($this->payments as $payment) {
            if (blank($payment['amount'])) {
                continue;
            }

            $total = bcadd($total, (string) $payment['amount'], 2);
        }

        return $total;
    }

    public function addPaymentRow(): void
    {
        $this->payments[] = ['method' => 'cash', 'amount' => '', 'reference_no' => ''];
    }

    public function removePaymentRow(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    public function checkout(): void
    {
        $this->checkoutError = null;
        $this->successMessage = null;
        $this->pendingApprovalMessage = null;

        abort_unless(Auth::user()?->can('create_sale') ?? false, 403);

        $this->loadActiveShift();

        if ($this->activeShift === null) {
            $this->checkoutError = 'Shift belum dibuka — buka shift terlebih dahulu sebelum berjualan.';

            return;
        }

        if ($this->cart === []) {
            $this->checkoutError = 'Keranjang masih kosong.';

            return;
        }

        $shiftId = $this->activeShift->id;
        $partnerId = $this->partnerId;
        $discountAmount = $this->discountAmount !== '' ? $this->discountAmount : '0';
        $cart = $this->cart;
        $payments = array_values(array_filter($this->payments, fn (array $payment): bool => filled($payment['amount'])));

        try {
            $result = DB::transaction(function () use ($shiftId, $partnerId, $discountAmount, $cart, $payments) {
                $sale = Sale::create([
                    'cashier_shift_id' => $shiftId,
                    'partner_id' => $partnerId ?: null,
                    'discount_amount' => $discountAmount,
                ]);

                foreach ($cart as $line) {
                    $sale->items()->create([
                        'product_id' => $line['type'] === 'product' ? $line['id'] : null,
                        'service_id' => $line['type'] === 'service' ? $line['id'] : null,
                        'quantity' => $line['quantity'],
                        'unit_price' => $line['unit_price'],
                        'serial_numbers' => $line['is_serialized']
                            ? self::parseSerialNumbers($line['serial_numbers_input'])
                            : null,
                    ]);
                }

                foreach ($payments as $payment) {
                    $sale->payments()->create([
                        'method' => $payment['method'],
                        'amount' => $payment['amount'],
                        'reference_no' => $payment['reference_no'] !== '' ? $payment['reference_no'] : null,
                    ]);
                }

                return app(FinalizeSaleAction::class)->execute($sale);
            });
        } catch (SaleValidationException|InsufficientStockException|StockDocumentValidationException $e) {
            // R7/AC-10 (stok kurang) dan seluruh validasi SaleValidationException
            // (T4.1/T4.2) membatalkan TRANSAKSI DATABASE lewat rollback — tidak
            // ada draft yatim tersisa. Keranjang (state komponen, bukan DB)
            // TETAP utuh supaya kasir tinggal memperbaiki dan mencoba lagi,
            // bukan mengetik ulang seluruh transaksi dari nol.
            $this->checkoutError = $e->getMessage();

            return;
        }

        if ($result->state === DocumentState::Draft) {
            // AP-01: diskon melebihi TH1/TH2 — dokumen tersimpan sebagai draft
            // menunggu approval, stok BELUM disentuh (FinalizeSaleAction belum
            // memanggil StockLedgerService pada jalur ini). Pesan menegaskan
            // status sebenarnya, bukan "berhasil" yang menyesatkan.
            $this->pendingApprovalMessage = 'Diskon melebihi batas — menunggu approval Admin/Owner. '
                .'Transaksi tersimpan sebagai draft (belum final, stok belum berkurang).';
        } else {
            $this->lastFinalizedSaleId = (string) $result->id;
            // U8: transaksi tersimpan LOKAL, tidak bergantung status
            // konektivitas HQ sama sekali — pesan menegaskan ini eksplisit.
            $this->successMessage = sprintf(
                'Transaksi %s tersimpan. Data tersimpan di server cabang ini — status koneksi HQ tidak memengaruhi keberhasilan transaksi.',
                $result->document_number,
            );
        }

        $this->resetCart();
    }

    /**
     * @return array<int, string>
     */
    private static function parseSerialNumbers(string $rawInput): array
    {
        $lines = preg_split('/[\r\n,]+/', $rawInput) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $serial): bool => $serial !== ''));
    }

    private function resetCart(): void
    {
        $this->cart = [];
        $this->partnerId = null;
        $this->discountAmount = '0';
        $this->payments = [['method' => 'cash', 'amount' => '', 'reference_no' => '']];
    }

    private function loadActiveShift(): void
    {
        $this->activeShift = CashierShift::query()
            ->where('cashier_id', Auth::id())
            ->where('state', DocumentState::Draft)
            ->latest('opened_at')
            ->first();
    }

    private function addLine(string $type, string $id, string $name, string $unitPrice, bool $isSerialized = false): void
    {
        // Produk serial TIDAK pernah digabung ke baris yang sudah ada — menaikkan
        // quantity tanpa serial baru akan membuat jumlah serial yang diisi kasir
        // tidak lagi cocok dengan quantity (R3, T4.9/UT5). Selalu baris baru.
        if (! $isSerialized) {
            foreach ($this->cart as $index => $line) {
                if ($line['type'] === $type && $line['id'] === $id) {
                    $this->cart[$index]['quantity'] = bcadd($this->cart[$index]['quantity'], '1', 4);

                    return;
                }
            }
        }

        $this->cart[] = [
            'key' => (string) Str::uuid(),
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'quantity' => '1',
            'unit_price' => $unitPrice,
            'is_serialized' => $isSerialized,
            'serial_numbers_input' => '',
        ];
    }

    public function render(): View
    {
        return view('pos.terminal', [
            'products' => $this->products(),
            'services' => $this->services(),
            'partners' => $this->partners(),
        ]);
    }
}
