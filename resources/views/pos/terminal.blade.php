{{--
    T4.5/UT1: pintasan papan ketik — mapping self-derived (HS-UI-RIGHTCLICK-v1.1
    tidak ada di repo untuk dirujuk persis), lihat docblock PosTerminal.
    PV7 tetap berlaku: murni fokus/aksi terprogram, tanpa transisi visual apa pun.
--}}
<div class="min-h-screen p-4" x-data
     @keydown.window.f1.prevent="$refs.searchInput && $refs.searchInput.focus()"
     @keydown.window.f2.prevent="$refs.lastQtyInput && $refs.lastQtyInput.focus()"
     @keydown.window.f3.prevent="$wire.checkout()"
     @keydown.window.f4.prevent="$wire.clearCart()"
     @keydown.window.escape="$wire.set('search', '')">
    {{-- U8 — banner konektivitas HQ, murni informatif, tidak pernah menonaktifkan checkout --}}
    @unless($this->isConnectedToHq())
        <div class="mb-4 rounded border-2 border-black bg-[var(--haen-gray-100)] p-3 text-sm">
            <strong>Terputus dari pusat.</strong>
            Transaksi tetap tersimpan secara lokal dan akan tersinkron otomatis begitu koneksi pulih.
        </div>
    @endunless

    {{-- Brand identity (HS-UI §5) — sebelumnya POS TIDAK menampilkan logo
         sama sekali, satu-satunya permukaan aplikasi tanpa branding HAEN
         KOMPUTER. Varian Primary Horizontal (cyan), sama dipakai topbar
         panel Filament (`filament-theme.css` `.fi-topbar .rc-brand-logo-primary`)
         — latar putih, konsisten lintas kedua permukaan. --}}
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/brand/HK-LOGO-01-Primary-Horizontal.svg') }}"
                 alt="HAEN KOMPUTER" style="width: 180px; height: auto;">
            <h1 class="text-xl font-semibold">POS — {{ auth()->user()->name }}</h1>
        </div>
        <a href="{{ route('filament.admin.pages.dashboard') }}" class="text-sm underline">Kembali ke Back Office</a>
    </div>

    @if ($activeShift === null)
        {{-- Tanpa shift terbuka, kasir tidak bisa berjualan sama sekali --}}
        <div class="mx-auto max-w-md rounded border-2 border-black p-6">
            <h2 class="mb-4 text-lg font-semibold">Buka Shift Kasir</h2>

            @if ($shiftError)
                <div class="mb-3 rounded border-2 border-[var(--state-danger)] p-2 text-sm">{{ $shiftError }}</div>
            @endif

            <label class="mb-1 block text-sm font-medium">Kas Awal</label>
            <input type="number" step="0.01" min="0" wire:model="openingCashInput"
                   class="mb-4 w-full rounded border-2 border-black p-2">

            <button wire:click="openShift" class="pos-btn-primary w-full rounded p-3 font-semibold">
                Buka Shift
            </button>
        </div>
    @else
        <div class="mb-4 flex items-center justify-between rounded border-2 border-black p-2 text-sm">
            <span>Shift terbuka — kas awal Rp{{ number_format((float) $activeShift->opening_cash, 0, ',', '.') }}</span>
            <a href="{{ route('filament.admin.resources.cashier-shifts.index') }}" class="underline">Tutup Shift</a>
        </div>

        @if ($successMessage)
            <div class="mb-4 rounded border-2 border-[var(--state-success)] p-3 text-sm">
                {{ $successMessage }}
                @if ($lastFinalizedSaleId)
                    <a href="{{ route('pos.receipt', $lastFinalizedSaleId) }}" target="_blank"
                       class="pos-btn-primary ml-2 inline-block rounded px-3 py-1">Cetak Nota</a>
                    {{-- NT-05: kegagalan cetak TIDAK membatalkan transaksi — transaksi
                         sudah tersimpan di atas; nota bisa dicetak ulang kapan pun. --}}
                    <p class="mt-1 text-xs text-[var(--haen-gray-600)]">
                        Transaksi tersimpan. Bila pencetakan gagal, klik "Cetak Nota" kapan saja untuk mencetak ulang.
                    </p>
                @endif
            </div>
        @endif

        @if ($pendingApprovalMessage)
            <div class="mb-4 rounded border-2 border-[var(--state-warning)] p-3 text-sm">{{ $pendingApprovalMessage }}</div>
        @endif

        @if ($checkoutError)
            <div class="mb-4 rounded border-2 border-[var(--state-danger)] p-3 text-sm">{{ $checkoutError }}</div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- Katalog produk/jasa --}}
            <div class="lg:col-span-2">
                <input type="search" x-ref="searchInput" wire:model.live.debounce.300ms="search"
                       wire:keydown.enter.prevent="addFirstMatch" placeholder="Cari produk atau jasa... (Enter=tambah hasil pertama)"
                       class="mb-1 w-full rounded border-2 border-black p-2">
                <p class="mb-3 text-xs text-[var(--haen-gray-600)]">
                    F1 pencarian &middot; F2 qty baris terakhir &middot; F3 bayar &middot; F4 kosongkan keranjang &middot; Esc reset pencarian
                </p>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                    @foreach ($products as $product)
                        <button wire:click="addProduct('{{ $product->id }}')"
                                class="relative rounded border-2 border-black p-2 text-left">
                            {{-- POS-05: stok nol tetap tampil, tidak disembunyikan --}}
                            @if ((float) $product->qty_on_hand <= 0)
                                <span class="pos-badge-out-of-stock absolute right-1 top-1 rounded px-1 text-xs">HABIS</span>
                            @endif
                            <div class="text-sm font-medium">{{ $product->name }}</div>
                            <div class="text-xs text-[var(--haen-gray-600)]">{{ $product->sku }}</div>
                            <div class="text-sm font-semibold">Rp{{ number_format((float) $product->selling_price, 0, ',', '.') }}</div>
                        </button>
                    @endforeach

                    @foreach ($services as $service)
                        <button wire:click="addService('{{ $service->id }}')"
                                class="rounded border-2 border-dashed border-black p-2 text-left">
                            <div class="text-sm font-medium">{{ $service->name }}</div>
                            <div class="text-xs text-[var(--haen-gray-600)]">Jasa</div>
                            <div class="text-sm font-semibold">Rp{{ number_format((float) $service->price, 0, ',', '.') }}</div>
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Keranjang + pembayaran --}}
            <div class="rounded border-2 border-black p-3">
                <h2 class="mb-2 font-semibold">Keranjang</h2>

                @if (count($cart) === 0)
                    <p class="text-sm text-[var(--haen-gray-600)]">Belum ada baris.</p>
                @endif

                @foreach ($cart as $index => $line)
                    <div class="mb-2 border-b border-[var(--haen-gray-300)] pb-2 text-sm" wire:key="cart-{{ $line['key'] }}">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div>{{ $line['name'] }}</div>
                                <div class="text-xs text-[var(--haen-gray-600)]">Rp{{ number_format((float) $line['unit_price'], 0, ',', '.') }} / unit</div>
                            </div>
                            <input type="number" step="0.0001" min="0.0001"
                                   @if ($loop->last) x-ref="lastQtyInput" @endif
                                   wire:model.blur="cart.{{ $index }}.quantity"
                                   class="w-16 rounded border border-black p-1 text-right">
                            <button wire:click="removeLine('{{ $line['key'] }}')" class="ml-2 text-[var(--state-danger)]" aria-label="Hapus">&times;</button>
                        </div>

                        @if ($line['is_serialized'] ?? false)
                            {{-- T4.9/UT5: produk serial wajib mengisi satu serial number per unit
                                 sebelum pembayaran diproses (divalidasi ulang di server saat finalisasi). --}}
                            <label class="mt-1 block text-xs font-medium">
                                Serial Number (satu per baris, {{ $line['quantity'] }} dibutuhkan)
                            </label>
                            <textarea wire:model.blur="cart.{{ $index }}.serial_numbers_input" rows="2"
                                      placeholder="Ketik/scan satu serial per baris"
                                      class="w-full rounded border-2 border-black p-1 text-xs"></textarea>
                        @endif
                    </div>
                @endforeach

                <div class="mt-3 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>Rp{{ number_format((float) $this->subtotal(), 0, ',', '.') }}</span></div>
                </div>

                <label class="mt-2 block text-xs font-medium">Diskon Total</label>
                <input type="number" step="0.01" min="0" wire:model.blur="discountAmount" class="mb-2 w-full rounded border-2 border-black p-2">

                <label class="block text-xs font-medium">Pelanggan (kosongkan untuk walk-in)</label>
                <select wire:model="partnerId" class="mb-2 w-full rounded border-2 border-black p-2">
                    <option value="">— Walk-in —</option>
                    @foreach ($partners as $partner)
                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                    @endforeach
                </select>
                <p class="mb-2 text-xs text-[var(--haen-gray-600)]">Pembayaran boleh kurang dari total (DP) HANYA bila Pelanggan diisi.</p>

                <div class="mb-2 flex justify-between border-t border-black pt-2 font-semibold">
                    <span>Total</span><span>Rp{{ number_format((float) $this->total(), 0, ',', '.') }}</span>
                </div>

                <h3 class="mb-1 font-semibold">Pembayaran</h3>
                @foreach ($payments as $index => $payment)
                    <div class="mb-2 flex gap-1" wire:key="payment-{{ $index }}">
                        <select wire:model="payments.{{ $index }}.method" class="rounded border-2 border-black p-1 text-sm">
                            <option value="cash">Tunai</option>
                            <option value="card">Kartu</option>
                            <option value="transfer">Transfer</option>
                            <option value="qris">QRIS</option>
                            <option value="other">Lainnya</option>
                        </select>
                        <input type="number" step="0.01" min="0" placeholder="Jumlah"
                               wire:model.blur="payments.{{ $index }}.amount"
                               class="w-24 rounded border-2 border-black p-1 text-sm">
                        <button wire:click="removePaymentRow({{ $index }})" class="text-[var(--state-danger)]" aria-label="Hapus pembayaran">&times;</button>
                    </div>
                @endforeach
                <button wire:click="addPaymentRow" class="mb-3 text-sm underline">+ Tambah Pembayaran</button>

                <div class="mb-3 flex justify-between text-sm">
                    <span>Terbayar</span><span>Rp{{ number_format((float) $this->paymentsTotal(), 0, ',', '.') }}</span>
                </div>

                <button wire:click="checkout" class="pos-btn-primary w-full rounded p-3 font-semibold">
                    Bayar
                </button>
            </div>
        </div>
    @endif
</div>
