# CLAUDE.md — RIGHTCLICK

Berkas konteks proyek untuk sesi Claude Code. Dibaca otomatis di awal setiap sesi.

**Proyek:** RIGHTCLICK — ERP internal HAEN KOMPUTER
**Unit pemilik:** HAEN KOMPUTER (HK) · **Unit pelaksana:** HAEN SOFTWARE (HS)
**Versi:** 1.0 · Agustus 2026
**Gate:** G3 diajukan — development siap dimulai

---

## 1. Project Overview & Business Objective

RIGHTCLICK adalah rebuild total ERP Arabica, sistem operasional internal HAEN KOMPUTER — toko retail komputer dengan **dua cabang** di Kudus, Jawa Tengah.

Rebuild dipilih, bukan patch, karena sistem lama memiliki kerentanan keamanan dan masalah integritas data yang tidak dapat diperbaiki tanpa membongkar fondasinya.

**Tujuan bisnis:** satu sumber kebenaran atas stok, transaksi, dan uang di seluruh cabang — beroperasi penuh meski internet terputus, dengan setiap perubahan data terekam dan dapat diaudit.

RIGHTCLICK adalah **alat internal**. Bukan produk berbayar, bukan SaaS. Multi-tenancy dikeluarkan permanen dari desain.

---

## 2. Problem Statement & Target Users

### Masalah yang diselesaikan

| # | Masalah pada sistem berjalan |
|---|---|
| P1 | Kerentanan keamanan dan masalah integritas data |
| P2 | Tidak ada jejak audit memadai — perubahan data tidak dapat ditelusuri |
| P3 | Kontrol akses tidak terpisah per peran |
| P4 | Harga pokok tidak dihitung per batch — margin tidak akurat |
| P5 | Dua cabang belum terkonsolidasi |
| P6 | Operasi kasir bergantung pada internet |

### Pengguna

| Peran | Kebutuhan utama | Batasan penting |
|---|---|---|
| **Kasir** | Transaksi cepat, tidak terhenti gangguan teknis, pertanggungjawaban kas jelas | Tidak melihat harga pokok dan margin; tidak dapat void |
| **Staf Gudang** | Pencatatan barang masuk-keluar akurat, status barang jelas | Tidak mengubah stok tanpa alasan tercatat; tidak melihat nilai |
| **Admin** | Kontrol master data, procurement, kas, approval | Tidak menghapus data transaksi permanen |
| **Owner** | Melihat kondisi lintas cabang, menyetujui, menelusuri | — |

---

## 3. MVP Scope

### Dibangun — 5 fase, masing-masing 1 minggu

| Fase | Modul | Cakupan |
|---|---|---|
| 1 | Platform & Access Control | Auth, peran, permission, audit log, penomoran, document state, soft delete, approval, backup |
| 2 | Master Data | Partner, produk, kategori, satuan, cabang, karyawan, jasa |
| 3 | Inventory Core | Batch, ledger mutasi, HPP FIFO, status stok, opname, adjustment, transfer, serial |
| 4 | Sales & POS | Penjualan retail, multi-payment, DP, diskon, retur, shift kasir, cetak nota, operasi offline |
| 5 | Procurement + Kas + Sinkronisasi | PO, penerimaan, faktur, hutang, kas, piutang, outbox, sync API |

### **Di luar MVP** — jangan dibangun

Servis · Trade-in & Buyback · Titip Jual · Nabung PC · Rakitan PC · Warranty · AMC · Home Service · Akuntansi penuh (jurnal, buku besar, laba rugi, neraca) · HRIS & Payroll · CRM · Dashboard · Loyalty · Notifikasi WhatsApp · AI Assistant · Serial Registry penuh · Integrasi Webstore

> Modul servis, trade-in, titip jual, dan nabung PC **sudah berjalan manual** di toko dan akan tetap manual selama MVP. Konsumsi part servis dicatat melalui stock adjustment berkategori `service_consumption_manual`.

---

## 4. Technology Stack

Deviasi resmi dari HAEN Engineering Standard v1.1, disetujui COO (`HS-MEMO-DEVIASI-STACK-RIGHTCLICK-v1.0`).

| Layer | Teknologi |
|---|---|
| Framework | Laravel 12 (PHP 8.3) |
| Antarmuka | Filament 4 (Livewire) |
| Database | PostgreSQL 16 |
| Auth | Laravel Auth + Policies + `spatie/laravel-permission` |
| Antrean & cache | Redis |
| Deployment | Docker Compose, server lokal per cabang |
| Reverse proxy | Caddy (HTTPS internal) |
| VPN antar node | WireGuard |
| CI | GitHub Actions — Pest, PHPStan level 6, `composer audit` |

**Alasan deviasi:** POS wajib beroperasi saat internet eksternal terputus, di dua cabang. Supabase dan Vercel tidak dapat memenuhi ini.

**Tidak tersedia RLS.** Otorisasi sepenuhnya di lapisan aplikasi — ini kompensasi wajib, bukan opsional.

---

## 5. System Architecture

### Topologi tiga node

```
Cabang A (server lokal)          Cabang B (server lokal)
├── POS, stok, servis A          ├── POS, stok, servis B
└── master data (read-only)      └── master data (read-only)
         └────── WireGuard VPN ──────┘
                     │
              Node HQ (container di server Cabang Utama)
              ├── master data (satu-satunya penulis)
              ├── konsolidasi transaksi & laporan
              └── feed webstore (Rilis 5)
```

**Aturan mendasar:** node cabang **tidak pernah** memanggil HQ secara sinkron dalam alur transaksi. Seluruh komunikasi antar node asinkron.

| Data | Arah | Mekanisme |
|---|---|---|
| Master data + users | HQ → Cabang | PostgreSQL logical replication (read-only di cabang) |
| Transaksi final | Cabang → HQ | Outbox + queue worker + API sinkronisasi |

### Lapisan aplikasi

```
app/
├── Domain/           ← aturan bisnis, tanpa ketergantungan framework
├── Application/      ← Services, Actions, DTOs
├── Infrastructure/   ← Persistence, Sync, Printing, Queue
└── Presentation/     ← Filament (back office) + Pos (Livewire)
```

**POS dibangun di luar Filament** sebagai halaman Livewire mandiri — kebutuhan kecepatan dan layar sentuh berbeda mendasar dari pola CRUD.

---

## 6. Aturan Lintas-Modul — MENGIKAT

Pelanggaran atas aturan ini adalah kegagalan Definition of Done, bukan preferensi gaya.

| # | Aturan |
|---|---|
| **R1** | `stock_mutations` adalah **satu-satunya sumber kebenaran stok**. Kuantitas tersedia dihitung dari mutasi, tidak disimpan sebagai angka yang dapat diedit. Hanya `StockLedgerService` yang boleh menulis |
| **R2** | HPP **FIFO per batch**. `unit_cost` batch dicatat dari nilai faktur **TERMASUK PPN** — HAEN KOMPUTER non-PKP, PPN tidak dapat dikreditkan |
| **R3** | Serial dicatat sebagai field pada baris transaksi sejak MVP. Unit registry penuh pasca-MVP |
| **R4** | Seluruh dokumen transaksi: **draft → final → void**. Dokumen final tidak dapat diedit. Koreksi via void beralasan + dokumen baru |
| **R5** | Soft delete di seluruh tabel transaksi. **Kecuali** `stock_mutations` dan `audit_logs` yang append-only tanpa soft delete |
| **R6** | Penomoran dokumen terpusat, memuat kode cabang, dijamin unik pada transaksi bersamaan (`SELECT ... FOR UPDATE`) |
| **R7** | Pengurangan stok menggunakan **penguncian pesimistis**. Stok tidak pernah boleh negatif |
| **R8** | POS beroperasi penuh saat internet eksternal terputus. Fungsi yang butuh internet boleh tertunda, **tidak boleh memblokir transaksi** |
| **R9** | Clean start — tanpa migrasi data historis. Stok awal via opname fisik, saldo keuangan via jurnal saldo awal |
| **R10** | Matriks permission adalah deliverable Fase 1, bukan tambalan belakangan |
| **R11** | Audit log pada seluruh aksi sensitif: aktor, waktu, nilai sebelum, nilai sesudah |
| **R12** | Stok bersifat **branch-scoped**. Transfer antar cabang = dua dokumen (kirim + terima) dengan status transit |
| **R13** | **Tidak ada perhitungan PPN pada penjualan.** POS tidak menghitung, menampilkan, atau mencetak baris PPN |

---

## 7. Database Design

### Konvensi

| Aspek | Aturan |
|---|---|
| Primary key | `uuid` — **UUID v7**, dibangkitkan aplikasi |
| Penamaan | `snake_case`, tabel bentuk jamak |
| Kolom wajib | `id`, `created_at`, `updated_at`, `deleted_at` |
| Jejak pengguna | `created_by`, `updated_by` pada tabel transaksi |
| Cabang | `branch_id` wajib pada seluruh tabel transaksi |
| Uang | `numeric(18,2)` — **tidak pernah** float |
| Kuantitas | `numeric(18,4)` |
| Status | `varchar` + CHECK constraint, dipetakan ke PHP Enum |

**Mengapa UUID v7:** primary key harus unik lintas tiga node tanpa koordinasi. Auto-increment akan bertabrakan saat transaksi dua cabang digabung di HQ.

### Klasifikasi sinkronisasi per tabel

| Kelas | Tabel |
|---|---|
| **REPLICATED** (tulis di HQ, read-only di cabang) | `branches`, `users`, `roles`, `permissions`, `user_branches`, `partners`, `products`, `product_categories`, `units`, `services`, `employees`, `settings` |
| **SYNCED** (tulis di cabang → HQ) | `stock_batches`, `stock_mutations`, `sales`, `sale_items`, `sale_payments`, `cashier_shifts`, `purchase_orders`, `goods_receipts`, `purchase_invoices`, `cash_entries`, `audit_logs`, dst. |
| **LOCAL** | `stock_balances`, `document_sequences`, `outbox_events`, `sync_states`, `sessions`, `jobs` |

### Tabel kunci

**`stock_batches`** — `unit_cost` TERMASUK PPN
**`stock_mutations`** — append-only, `reference_type` + `reference_id` WAJIB
**`stock_balances`** — cache turunan, hanya ditulis `StockLedgerService`, dapat dibangun ulang via `php artisan stock:rebuild-balances`
**`outbox_events`** — `id` sebagai idempotency key, ditulis dalam transaksi yang sama dengan dokumen

### Algoritma FIFO — urutan penguncian wajib

```
BEGIN;
  1. SELECT document_sequences ... FOR UPDATE      ← kunci pertama
  2. SELECT stock_batches WHERE qty_remaining > 0
     ORDER BY received_at ASC FOR UPDATE           ← kunci kedua
  3. Konsumsi batch berurutan; bila kurang → ROLLBACK dengan pesan jelas
  4. Update qty_remaining
  5. INSERT stock_mutations (satu baris per batch)
  6. UPDATE stock_balances
  7. INSERT sales, sale_items, sale_payments
  8. INSERT receivables bila belum lunas
  9. INSERT cash_entries bila ada pembayaran tunai
 10. INSERT audit_logs
 11. INSERT outbox_events                          ← WAJIB di transaksi yang sama
COMMIT;
```

**Urutan penguncian konsisten di seluruh kode adalah satu-satunya cara mencegah deadlock.**

Detail lengkap: `HS-DB-RIGHTCLICK-v1.0` (44 tabel, constraint C1–C15, indeks).

---

## 8. API Specification

MVP bersifat server-rendered. **Tidak ada API CRUD untuk pengguna akhir.**

Yang ada hanya protokol sinkronisasi antar node, diakses melalui VPN dengan token per node.

| Endpoint | Fungsi |
|---|---|
| `POST /api/v1/sync/events` | Kirim batch event dari outbox cabang (maks 500 event, 5 MB) |
| `POST /api/v1/sync/ack` | Konfirmasi batch diproses |
| `GET /api/v1/sync/health` | Status node, lag, jumlah tertunda |
| `POST /api/v1/sync/master-check` | Verifikasi replikasi master data (terjadwal 15 menit) |
| `GET /api/v1/sync/master-snapshot/{table}` | Pemulihan bila replikasi rusak |
| `POST /api/v1/sync/partner-upsert` | Partner dibuat lokal saat HQ tak terjangkau |

### Empat status hasil per event — penting

| Status | Arti | Tindakan cabang |
|---|---|---|
| `accepted` | Diterima | Tandai `sent` |
| `duplicate` | Sudah pernah diterima | Tandai `sent` — **bukan kegagalan** |
| `deferred` | Valid, dependensi belum tiba | Biarkan `pending`, coba ulang |
| `rejected` | Tidak dapat diproses | Tandai `failed`, tampilkan di panel admin |

> `deferred` dipisahkan dari `rejected` secara sengaja. `sale.finalized` merujuk `batch_id` dari `goods_receipt.finalized`; bila urutannya terbalik itu bukan kesalahan. Memperlakukannya sebagai gagal menghasilkan banjir peringatan palsu — dan panel yang diabaikan sama saja dengan tidak ada panel.

**Idempotensi mutlak:** `event_id` sebagai primary key `processed_events`. Pengiriman ulang adalah kondisi normal, bukan pengecualian.

Detail lengkap: `HS-API-RIGHTCLICK-v1.0`.

---

## 9. UI Requirements

Brand Identity HAEN KOMPUTER berstatus **Final**. Seluruh permukaan mengikuti `HK-CRE-Brand-Identity-Guidelines-v1.0`.

### Design token

| Token | Nilai |
|---|---|
| `--haen-cyan` | `#00B4D4` — aksen, tombol utama, tautan, penanda aktif |
| `--haen-black` | `#000000` — teks utama, sidebar, header tabel |
| `--haen-white` | `#FFFFFF` — latar dominan |
| `--haen-cyan-hover` | `#0099B5` |
| `--haen-cyan-subtle` | `#E6F7FB` |
| `--haen-gray-900/600/300/100` | `#1A1A1A` / `#666666` / `#D4D4D4` / `#F5F5F5` |
| `--state-success/warning/danger/neutral` | `#0E9F6E` / `#D97706` / `#DC2626` / `#666666` |

**Proporsi wajib: 60% putih, 30% hitam, 10% cyan.** Cyan adalah aksen, bukan latar.

**Tipografi:** Inter — Regular, Medium, SemiBold, Bold saja. **Dimuat lokal dari `resources/fonts`, bukan CDN** — antarmuka tidak boleh kehilangan tipografinya saat internet terputus.

### Aturan kontras mengikat

| Kombinasi | Rasio | Ketentuan |
|---|---|---|
| Teks cyan di atas putih | ≈2,6:1 | ❌ **Dilarang untuk teks isi.** Hanya ikon, garis aksen, tautan ≥16px Medium bergaris bawah |
| Teks putih di atas cyan | 4,6:1 | ⚠️ Hanya untuk teks ≥16px atau ≥14px Bold |

Warna semantik **hanya** untuk lencana status dan ikon — tidak pernah untuk tombol. Cyan tetap satu-satunya warna aksi.

### Ketentuan antarmuka yang paling sering terlewat

| # | Ketentuan |
|---|---|
| U8 | Saat terputus, pesan **wajib menegaskan transaksi tetap tersimpan**. Kasir yang mengira transaksinya hilang akan mencatat ulang manual |
| U9 | Kolom nilai di Penerimaan Barang dan Faktur Pembelian **wajib berlabel "termasuk PPN"** dengan teks bantuan permanen |
| NT-05 | Kegagalan printer **tidak** membatalkan transaksi. Pesan: *"Transaksi tersimpan. Pencetakan gagal — coba cetak ulang."* |
| POS-05 | Produk berstok nol **tetap tampil** dengan lencana "HABIS", tidak disembunyikan |
| POS-06 | Harga pokok dan margin **tidak pernah dikirim ke klien** bagi Kasir — disaring di query, bukan di tampilan |
| PV7 | Tanpa animasi transisi pada POS |

Detail lengkap: `HS-UI-RIGHTCLICK-v1.1`. Mockup referensi (bila ada): `02_WORKING/design/`.

---

## 10. Otorisasi

Tiga lapis yang wajib dilewati setiap aksi:

```
1. Permission (spatie)  → boleh melakukan aksi ini sama sekali?
2. Policy per model     → boleh terhadap record spesifik ini?
3. BranchScope          → record ini di cabang pengguna?
```

- Lapis 1 dan 2 gagal → **403** + audit log `access_denied`
- Lapis 3 gagal → **404**, bukan 403 — memberi tahu dokumen ada tetapi tidak boleh diakses sudah membocorkan informasi

### Peran

`owner` · `admin` · `kasir` · `gudang` · `viewer`

### Nilai ambang (di tabel `settings`, dapat diubah per cabang)

| Kode | Parameter | Nilai |
|---|---|---|
| TH1 | Diskon maks Kasir per transaksi | **Rp 100.000** |
| TH2 | Diskon maks Admin per transaksi | **Rp 300.000** |
| TH3 | Penyesuaian stok tanpa approval Owner | **Rp 5.000.000**/dokumen |
| TH3b | Kumulatif penyesuaian per Admin per bulan | **Rp 15.000.000** |
| TH4 | PO tanpa approval Owner | **Rp 10.000.000** |
| TH5a / TH5b | Perubahan harga jual | naik **>10%** / turun **>5%** |
| TH5c | Harga jual di bawah HPP batch tertua | **selalu** approval |

**Ambang diskon berlaku pada TOTAL transaksi, bukan per baris** — tanpa aturan ini diskon tinggal dipecah untuk lolos approval.

### Aturan struktural

| # | Aturan |
|---|---|
| P1 | **Tidak ada peran yang dapat update atau delete `audit_logs`** — termasuk Owner |
| P4 | Setiap model transaksi **wajib** memiliki Policy. Model tanpa Policy menggagalkan architecture test |
| P6 | Kolom sensitif disaring **di lapisan query**. Menyembunyikan di tampilan tidak dianggap kontrol |

Detail lengkap: `HS-PERM-RIGHTCLICK-v1.1` (58 permission, matriks lengkap).

---

## 11. Development Tasks

62 task dalam 5 fase. Setiap task dirancang untuk satu sesi Claude Code, disusun berdasarkan ketergantungan.

**Daftar lengkap: `HS-TASKS-RIGHTCLICK-v1.1`** — memuat rekomendasi model Claude per task (MD1–MD4).

**Kemajuan:**

| ID | Status | Catatan |
|---|---|---|
| T1.1–T1.5 | ✅ Selesai | Bootstrap, CI, fondasi model, identity tables, spatie permission + 58 permission/5 peran |
| T1.6 | ✅ Selesai | `AuditLogService` (`AuditService`) + tabel `audit_logs` append-only + trait `Auditable` (observer otomatis, diterapkan pada `Setting` dan `Approval`) |
| T1.7 | ✅ Selesai | `DocumentNumberService` + `document_sequences`, `SELECT ... FOR UPDATE` |
| T1.8 | ✅ Selesai | Trait `HasDocumentState` + `DocumentStateService`, draft→final→void |
| T1.9 | ✅ Selesai | Tabel `settings` + seeder ambang TH1–TH5c + `ApprovalService`/tabel `approvals` |
| T1.10 | ✅ Selesai | Tema Filament: warna HAEN brand + Inter lokal + sidebar hitam + dark mode nonaktif + 6 varian logo SVG tersambung (login/topbar: Primary Horizontal, sidebar: Reverse, favicon: Badge/Avatar) via `->viteTheme()` + `->brandLogo()`. NV3 (logo→Wordmark Only saat sidebar diciutkan) sengaja ditunda, lihat catatan di `AdminPanelProvider` |
| T1.11 | ✅ Selesai | Halaman login kustom (`App\Presentation\Filament\Auth\Login`) — field `username` (bukan `email`, tidak cocok skema `users`), banner "Terputus dari pusat" via `NodeConnectivityService` (deteksi `pg_stat_subscription`), tiga keadaan diuji (normal/terputus/kredensial salah) |
| T1.12 | ✅ Selesai | Service `backup` — pg_dump harian + enkripsi + retensi 30/12/12 + off-site + uji restore bulanan, diverifikasi end-to-end |
| T1.13 | ✅ Selesai | Pengujian otorisasi negatif PT1–PT16 — 7 dari 16 diuji sebagai mekanisme generik (PT3/4/6/8/9/10/11) memakai model yang sudah ada; 9 skenario (PT1/2/5/7/12/13/14/15/16) sengaja ditunda ke fase modul bisnis terkait (belum ada model Sale/PO/Product/StockAdjustment), dicatat eksplisit di `tests/Feature/AuthorizationScenariosTest.php`. `User::canAccessPanel()` dan `locally_disabled_at` (fillable+cast yang sebelumnya hilang) baru diimplementasikan di sini |
| T1.14 | ✅ Selesai | `AuditLogResource` (baca-saja, tanpa halaman create/edit terdaftar sama sekali — PT6) + `SettingResource` (List+Edit saja, Owner+HQ only via `SettingPolicy`, field polimorfik Toggle/TextInput untuk `value` boolean/numerik). Permission `view_audit_logs`/`export_audit_logs` diseed di sini (sebelumnya "Direncanakan" T1.14 di `HS-PERM-RIGHTCLICK-v1.2`) |

> **Catatan T1.10 (penting untuk task Filament berikutnya):** panel Filament TIDAK memakai `resources/css/app.css` (entry Vite default Laravel) sama sekali — ia butuh entry terpisah didaftarkan lewat `->viteTheme()`, di sini `resources/css/filament/admin/theme.css`. Tanpa `->viteTheme()`, CSS kustom apa pun di `filament-theme.css` DIAM-DIAM tidak pernah dimuat browser (tidak ada error, panel terlihat "normal" memakai tema bawaan Filament) — bug ini sempat lolos tanpa terdeteksi karena verifikasi sebelumnya hanya PHPStan/Pint/test PHP, tidak pernah `npm run build` maupun cek browser sungguhan. Dua bug tambahan yang ditemukan saat memperbaiki ini: (1) `@apply bg-cyan`/`text-cyan`/`border-cyan` bukan utility Tailwind valid — token warna kustom (`--haen-cyan`) harus dipakai lewat CSS biasa (`background-color: var(--haen-cyan)`), bukan `@apply`; (2) komentar CSS yang memuat teks berisi urutan karakter `*/` (mis. `--primary-*/--gray-*`) menutup komentar C-style secara prematur dan merusak parsing file — hindari `*/` literal di dalam teks komentar CSS.
>
> **Catatan T1.11 (kritis, memengaruhi SELURUH test suite sebelumnya):** `phpunit.xml` men-set `APP_ENV=testing`/`DB_DATABASE=rightclick_testing`, tapi env var itu **tidak pernah benar-benar aktif** — `docker-compose.yml` mengisi `APP_ENV=local`/`DB_DATABASE=rightclick` di level container lewat `env_file: .env`, dan nilai itu sudah ada di `$_SERVER` sebelum PHPUnit sempat jalan. `vlucas/phpdotenv` (dipakai `env()`/`config()` Laravel) membaca `$_SERVER` **lebih dulu** daripada `$_ENV`/`putenv()` (`RepositoryBuilder::DEFAULT_ADAPTERS` — `ServerConstAdapter` sebelum `EnvConstAdapter`), sementara `<env force="true">` PHPUnit **hanya** menyentuh `putenv()`/`$_ENV`, tidak pernah `$_SERVER`. Akibatnya: **seluruh test suite sebelum commit ini diam-diam berjalan di atas database `rightclick` (dev), bukan `rightclick_testing`** — pelanggaran B3 (§18) yang tidak pernah error karena `RefreshDatabase` membungkus tiap test dalam transaksi yang di-rollback. Efek sampingnya: `app()->runningUnitTests()` selalu `false`, yang diam-diam mematikan `Filament\Forms\Testing\TestsForms::fillFormDataForTesting()` — jadi `Livewire::test(...)->fillForm([...])` tidak pernah benar-benar mengisi state form di seluruh test suite manapun sampai sekarang, walau tidak ada test sebelumnya yang memakai `fillForm()` sehingga tidak pernah ketahuan. Perbaikan: `tests/bootstrap.php` (baru, didaftarkan lewat atribut `bootstrap` di `phpunit.xml`) menyalin `$_ENV` ke `$_SERVER` setelah PHPUnit menerapkan seluruh nilai `<env>`, memastikan `ServerConstAdapter` melihat nilai yang sama. Tambahkan `force="true"` di setiap `<env>` `phpunit.xml` juga tetap perlu (dua perbaikan saling melengkapi, bukan salah satu cukup sendirian).
>
> **Catatan T1.13 (dua gap struktural ditemukan saat implementasi):**
> 1. `User` tidak pernah implement `Filament\Models\Contracts\FilamentUser`. Ini baru KELIHATAN setelah `APP_ENV` benar-benar `testing` (perbaikan T1.11 di atas) — `Filament\Http\Middleware\Authenticate` menolak SELURUH akses panel bagi model yang tidak implement `FilamentUser` di lingkungan non-`local` (fallback keamanan bawaan Filament). Diperbaiki dengan `User::canAccessPanel()`, sekaligus menegakkan PT10 (`is_active`/`locally_disabled_at`).
> 2. `locally_disabled_at` — kolom untuk mekanisme "nonaktif darurat offline" yang Policy-nya (`UserPolicy::manageEmergencyDisable()`) sudah ada sejak T1.4/T1.5 — **tidak pernah ada di `$fillable` maupun `casts()` model `User`**. Akibatnya fitur itu punya gerbang otorisasi yang lengkap tapi TIDAK PERNAH benar-benar bisa diisi lewat `update()`/`fill()` mana pun sejak awal dibuat. Diperbaiki di sini.
> 3. Percobaan pertama menegakkan PT10 lewat middleware kustom terpisah (`EnsureUserIsActive`, ditaruh sebelum `Authenticate::class` Filament di `->authMiddleware()`) **dibatalkan** — terbukti tidak konsisten terpanggil pada jalur permintaan Livewire full-page Filament (lolos pada permintaan pertama, terlewat pada permintaan berikutnya tanpa penjelasan yang berhasil ditelusuri tuntas dalam waktu wajar). Logout + invalidate sesi dipindah ke dalam `User::canAccessPanel()` sendiri — method itu terbukti SELALU dipanggil tepat sebelum keputusan izin, titik yang andal untuk efek samping ini.

**Fase 1 (Platform & Access Control) SELESAI** — T1.1–T1.14 lengkap. Gate dokumen ("Fase 1 tidak dinyatakan selesai sebelum T1.12 dan T1.13 lulus") terpenuhi.

### Fase 2 — Master Data

> **Peringatan penomoran:** sesi yang mengerjakan T2.1–T2.10 di bawah TIDAK memiliki akses ke `HS-TASKS-RIGHTCLICK-v1.1` (dokumen tidak ada di repositori). Penomoran T2.1–T2.10 di bawah disusun sendiri dari daftar modul §3 ("Partner, produk, kategori, satuan, cabang, karyawan, jasa") — sama seperti kesalahan yang dikoreksi untuk T1.x (lihat "Catatan koreksi label" di atas). Bila `HS-TASKS-RIGHTCLICK-v1.1` tersedia di sesi berikutnya, **cocokkan ulang** penomoran T2.x di bawah terhadap dokumen asli sebelum melanjutkan ke Fase 3.

| ID | Status | Catatan |
|---|---|---|
| T2.1 | ✅ Selesai | Cabang — ternyata SUDAH lengkap sejak T1.4/T1.5 (migration/model/`BranchPolicy`/seeder/tests) tanpa pernah diberi label eksplisit. Diverifikasi ulang (17 test), tidak ada kode baru |
| T2.2 | ✅ Selesai | `partners` (REPLICATED, tanpa `branch_id`) + enum `PartnerType` + `PartnerPolicy` + seeder + tests |
| T2.3 | ✅ Selesai | `product_categories` (REPLICATED, `parent_id` self-referencing) + `ProductCategoryPolicy` + seeder + tests. **Bug macro ditemukan:** `uuidPrimaryKey()` menyebabkan Postgres mengeksekusi `ALTER TABLE ADD FOREIGN KEY` sebelum `ALTER TABLE ADD PRIMARY KEY` untuk FK self-referencing dalam satu `Schema::create()` (SQLSTATE 42830) — diperbaiki dengan memisah FK ke `Schema::table()` terpisah setelah tabel dibuat. Migration self-referencing berikutnya harus memakai pola yang sama |
| T2.4 | ✅ Selesai | `units` (REPLICATED, flat lookup — sengaja tanpa `conversion_factor`, Product hanya punya satu `base_unit_id`) + `UnitPolicy` + seeder + tests |
| T2.5 | ✅ Selesai | `products` (REPLICATED) + `ProductPolicy` + seeder (10 produk) + tests. §16 #5 dipatuhi ketat — TIDAK ADA kolom harga beli, dengan test regresi eksplisit. **Gap terdokumentasi, bukan kelalaian:** TH5a/TH5b/TH5c (approval Owner untuk perubahan `selling_price` di atas ambang) BELUM ditegakkan — `ProductPolicy::update()` hanya cek `edit_products`, tidak bandingkan harga lama-vs-baru maupun HPP batch tertua (`stock_batches` belum ada, T3.1). Didokumentasikan di docblock `ProductPolicy` |
| T2.6 | ✅ Selesai | `employees` (REPLICATED, tanpa `branch_id` — HRIS/Payroll di luar MVP; `user_id`/`id_number` nullable) + `EmployeePolicy` + seeder (5 karyawan) + tests |
| T2.7 | ✅ Selesai | `services` (REPLICATED) + `ServicePolicy` + seeder (3 jasa) + tests. **Pembeda penting:** ini katalog harga jasa untuk baris POS, BUKAN modul "Servis" (tiket/penjadwalan/teknisi) yang eksplisit di luar MVP (§3) dan tetap manual — didokumentasikan di migration+model agar tidak disalahartikan sesi berikutnya |
| T2.8 | ✅ Selesai | Model/Policy `UserBranch` sudah ada sejak T1.4/T1.5 (sama seperti T2.1). Kerja nyata: `SetActiveBranchContext` middleware (`app/Http/Middleware`) — mengisi `BranchContext` dari sesi `active_branch_id` (**tervalidasi** terhadap `user_branches`, mencegah spoofing cabang) dengan fallback `default_branch_id`; didaftarkan di `AdminPanelProvider->authMiddleware()` setelah `Authenticate::class`. UI branch-switcher (penulis session) BELUM dibangun — kontrak baca middleware sudah siap |
| T2.9 | ✅ Selesai | 7 Filament Resource (Branch/Partner/ProductCategory/Unit/Product/Employee/Service), grup navigasi "Master Data". **Nyaris melanggar arsitektur:** sempat hendak implement `Filament\Support\Contracts\HasLabel` pada enum `PartnerType` (`App\Domain\Shared\Enums`) — dibatalkan karena melanggar aturan "Domain tidak bergantung framework" (`tests/Arch/LayeringTest.php`); opsi Select dibangun manual di layer Presentation. **Verifikasi:** Browser pane MCP tidak bisa menjangkau container di environment ini (limitasi jaringan sandbox — dikonfirmasi via `curl --resolve` bahwa aplikasi sendiri sehat; `rightclick.localhost` juga tidak resolve DNS di host ini). Diganti dengan 7 `*ResourceTest.php` (20 test) yang menjalankan `Livewire::test()->fillForm()->call('create')` end-to-end ke database sungguhan — verifikasi fungsional lebih ketat daripada klik visual |
| T2.10 | ✅ Selesai | Dua architecture test baru di `tests/Arch/LayeringTest.php`: (1) setiap Policy tabel REPLICATED memakai `GuardsMasterDataWrites` (data-driven, 10 model); (2) setiap entitas Master Data Fase 2 punya Filament Resource yang menunjuk model benar (data-driven, 7 resource). Test generik "setiap model punya Policy" (T1.5) otomatis mencakup model baru tanpa perubahan |

**Fase 2 (Master Data) SELESAI** — T2.1–T2.10 lengkap. Full test suite 250 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih di seluruh fase. Fase berikutnya: **Fase 3 — Inventory Core** (batch, ledger mutasi, HPP FIFO, status stok, opname, adjustment, transfer, serial — lihat simpul kritis T3.2 `StockLedgerService` di atas).

> **Catatan T1.9 (diselesaikan):** permission baru T1.9 memakai gaya `snake_case` mengikuti `PermissionSeeder` yang sudah ada, bukan notasi titik `HS-PERM-RIGHTCLICK-v1.1`. Ketidaksesuaian ini direkonsiliasi dengan menerbitkan `HS-PERM-RIGHTCLICK-v1.2` (revisi penamaan mengikuti kode, status Draft menunggu approval COO) — lihat §17.
>
> **Catatan koreksi label (penting):** sesi-sesi sebelumnya menandai task T1.6/T1.10/T1.11/T1.13/T1.14 tanpa membaca `HS-TASKS-RIGHTCLICK-v1.1` secara langsung — hanya menebak dari daftar modul umum di §3. Akibatnya pekerjaan yang sebelumnya diberi label "T1.11" (tabel `audit_logs`, `AuditLog` model, `AuditService`) sebenarnya adalah isi **T1.6** — sudah dikoreksi di tabel atas. Pekerjaan yang sebelumnya diberi label "T1.6" (Policy Branch/User/UserBranch) adalah bagian dari cakupan **T1.5** (architecture test Policy-per-model), bukan tugas terpisah — tidak ada kode yang perlu diubah, hanya labelnya yang salah. Tidak ada pekerjaan yang hilang, hanya penomoran yang perlu dibaca ulang terhadap dokumen asli sebelum melanjutkan task berikutnya.

### Tiga simpul yang tidak boleh dilewati

| Simpul | Alasan |
|---|---|
| **T3.2** — `StockLedgerService` sebagai penulis tunggal | Bila modul lain menulis mutasi langsung, FIFO dan penguncian terlewat, stok rusak tanpa jejak |
| **T5.7** — outbox dalam transaksi yang sama | Bila di luar transaksi, ada dokumen final yang tidak pernah sampai HQ dan tidak ada yang tahu |
| **T5.2** — `unit_cost` termasuk PPN | Bila diambil nilai DPP, seluruh HPP terlalu rendah dan margin dilaporkan lebih besar dari kenyataan |

### Fase 3 — Inventory Core

> **Peringatan penomoran (sama seperti Fase 2):** `HS-TASKS-RIGHTCLICK-v1.1` dan `HS-DB-RIGHTCLICK-v1.0` tetap tidak ada di repositori (dikonfirmasi ulang lewat pencarian menyeluruh sebelum memulai Fase 3). T3.1–T3.8 di bawah diturunkan sendiri dari §3/§11 dokumen ini, sama seperti T2.x. **Kode prefix dokumen** (`OPN`, `ADJ`, `TRO`, `TRI` — lihat `App\Domain\Shared\Enums\DocumentType`) juga diturunkan sendiri, BUKAN dikonfirmasi dari dokumen sumber — tercatat eksplisit di docblock enum itu sendiri untuk direkonsiliasi bila `HS-DB-RIGHTCLICK-v1.0` tersedia di sesi berikutnya.

| ID | Status | Catatan |
|---|---|---|
| T3.1 | ✅ Selesai | `stock_batches` (SYNCED, branch-scoped) + model + `StockBatchPolicy` (create/update/delete selalu `false` — satu-satunya penulis adalah `StockLedgerService`, T3.2) + factory + tests |
| T3.2 | ✅ Selesai | **Simpul kritis.** `stock_mutations` (append-only, sama bentuk dengan `audit_logs`) + `stock_balances` (LOCAL, cache turunan) + `StockMutationType` enum + `StockLedgerService` (`receive()`/`consume()` FIFO+lock/`reverseForReference()`/`availableQuantity()`) + `php artisan stock:rebuild-balances` + architecture test pemindaian berkas (`tests/Arch/StockMutationSingleWriterTest.php`) yang menegakkan R1 tanpa bergantung API arch Pest yang belum pasti tersedia. **AC-10 dibuktikan dengan DUA KONEKSI POSTGRES SUNGGUHAN** (`tests/Feature/StockLedgerConcurrencyTest.php`) — koneksi kedua diberi `lock_timeout` pendek dan HARUS gagal karena baris `stock_batches` masih dikunci `FOR UPDATE` koneksi pertama, membuktikan mekanisme penguncian asli, bukan simulasi. **Bug FIFO ditemukan saat T3.6:** dua batch dengan `received_at` identik (umum terjadi di lingkungan dengan resolusi jam kasar, atau input borongan) menghasilkan urutan konsumsi tidak deterministik — Postgres tidak menjamin urutan stabil untuk `ORDER BY` yang seri sepenuhnya. Diperbaiki dengan tiebreaker berlapis: `received_at` (utama, tanggal bisnis) → `created_at` → `id` (UUID v7, dijamin unik dan terurut waktu) |
| T3.3 | ✅ Selesai | Grup navigasi "Inventaris": `StockBalanceResource`/`StockBatchResource`/`StockMutationResource` (List+View saja, tanpa create/edit). **Permission baru ditemukan perlu:** `view_stock_cost` (permission ke-59, di luar 58 dari T1.5) — 14 permission Inventory asli tidak membedakan "lihat kuantitas" dari "lihat `unit_cost`", padahal §2 eksplisit menyatakan Gudang "tidak melihat nilai". Kolom `unit_cost` disaring lewat `getEloquentQuery()` (P6 — di lapisan query, bukan sekadar `visible()` UI) |
| T3.4 | ✅ Selesai | `StockOpnameType` enum (Periodic/OpeningBalance) + `stock_opnames`+`stock_opname_lines` + `FinalizeStockOpnameAction` (AC-12: selisih tanpa alasan ditolak; `system_qty` DIHITUNG ULANG saat finalisasi, tidak dipercaya dari draft — mencegah TOCTOU) + `VoidStockOpnameAction` + `StockOpnamePolicy` (ability custom `finalize()`/`void()`; saldo awal R9 mensyaratkan `adjust_opening_balance` TAMBAHAN) + Filament resource + tests |
| T3.5 | ✅ Selesai | `stock_adjustments`+`stock_adjustment_lines` (`reason` SELALU wajib) + `FinalizeStockAdjustmentAction` (TH3/TH3b dari `settings`; **Owner dikecualikan** — pembacaan struktural §10 bahwa tabel ambang adalah batas SEBELUM eskalasi ke Owner, Owner adalah puncak rantai persetujuan itu sendiri, bukan pola yang sudah ada di modul lain) + `ApproveStockAdjustmentAction` (menutup alur AP-01 — approval yang disetujui benar-benar diterapkan ke ledger, bukan cuma ganti status) + `VoidStockAdjustmentAction` + **PT15 tertutup** (`tests/Feature/FinalizeStockAdjustmentActionTest.php` — kumulatif bulanan per Admin melebihi TH3b meski satu dokumen sendiri di bawah TH3), menutup skenario yang didokumentasikan tertunda sejak T1.13 |
| T3.6 | ✅ Selesai | Transfer DUA DOKUMEN literal (R12) — `stock_transfers` (kirim, `branch_id`=asal) + `stock_transfer_lines` + `stock_transfer_line_batches` (rincian biaya FIFO per batch sumber, diwariskan apa adanya ke tujuan) + `stock_transfer_receipts` (terima, `branch_id`=tujuan, `unique(stock_transfer_id)` — MVP tanpa penerimaan sebagian) + `DispatchStockTransferAction`/`ReceiveStockTransferAction`/`VoidStockTransferAction`/`VoidStockTransferReceiptAction` (void kirim DITOLAK selama receipt aktif — mencegah stok tergandakan di dua cabang). **Keputusan desain:** dua tabel TETAP satu-`branch_id` masing-masing (pola `BelongsToBranch` yang sama seperti tabel lain) alih-alih model dual-branch-scope khusus — `stock_transfers.branch_id`=asal, `stock_transfer_receipts.branch_id`=tujuan, `dest_branch_id` di `stock_transfers` cuma referensi biasa. AC-11 dibuktikan eksplisit (`ReceiveStockTransferActionTest`): stok tidak tersedia di CABANG MANA PUN selama transit |
| T3.7 | ✅ Selesai | R3 (MVP-scoped, bukan unit registry penuh — batas §3 eksplisit). `serial_numbers` jsonb pada `stock_opname_lines`/`stock_adjustment_lines`/`stock_transfer_lines` (BUKAN `stock_mutations` — menghindari kerumitan memecah satu daftar serial ke beberapa baris ledger saat FIFO menyentuh >1 batch) + `SerialNumberValidationService` (jumlah harus pas, tanpa duplikat/kosong, produk non-serial dilarang mengisi) dipakai bersama tiga action T3.4–T3.6. Validasi hanya di sisi "naik"/perpindahan (opname selisih naik, adjustment arah naik, SELURUH baris transfer) — sisi turun/konsumsi tidak wajib serial, konsisten dengan filosofi "field saja, bukan registry lintas transaksi" |
| T3.8 | ✅ Selesai | Architecture test data-driven baru (7 resource Inventory Core, pola sama T2.10) menyusul test pemindaian berkas T3.2. Full test suite 341 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |

**Fase 3 (Inventory Core) SELESAI** — T3.1–T3.8 lengkap. Fase berikutnya: **Fase 4 — Sales & POS** (penjualan retail, multi-payment, DP, diskon, retur, shift kasir, cetak nota, operasi offline). PT1/PT2/PT5/PT7/PT13/PT14 (deferred sejak T1.13 ke Fase 4) masih menunggu modul Sales dibangun.

### Fase 4 — Sales & POS

> **Peringatan penomoran (sama seperti Fase 2/3):** `HS-TASKS-RIGHTCLICK-v1.1`/`HS-DB-RIGHTCLICK-v1.0` tetap tidak ada di repositori. T4.1–T4.5 di bawah diturunkan sendiri dari §3/§11 dokumen ini. Dua breadcrumb informal dari sesi sebelumnya sempat menebak penomoran berbeda (komentar `MigrationMacros`: "T4.3 (sales)"; komentar `ApprovalService`: "diskon POS T4.8") — KEDUANYA tidak dipakai, breadcrumb tersebut ditandai sebagai tebakan lama, bukan sumber otoritatif, sama seperti kesalahan label yang dikoreksi di Fase 1. Kode prefix `DocumentType::CashierShift` → `SFT` dan `App\Domain\Sales\Enums\PaymentMethod` juga self-derived — direkonsiliasi bila dokumen asli tersedia.

| ID | Status | Catatan |
|---|---|---|
| T4.1 | ✅ Selesai | **Simpul kritis** (Sale dan CashierShift saling bergantung — penjualan butuh shift terbuka, penutupan shift butuh data penjualan — jadi dibangun bersamaan, sama alasan T3.2). `cashier_shifts`/`sales`/`sale_items`/`sale_payments` (SYNCED) + model + Policy (termasuk `SaleItemPolicy`/`SalePaymentPolicy` tanpa API tulis independen, pola `StockAdjustmentLinePolicy`) + `FinalizeSaleAction` (FIFO consume via `StockLedgerService`, COGS `unit_cost_snapshot` dari hasil NYATA `Collection<StockConsumption>` bukan estimasi, baris jasa tidak menyentuh ledger) + `VoidSaleAction` + `CloseCashierShiftAction` (AC-16: `closing_cash_expected` dihitung ulang dari `sale_payments` tunai atas Sale FINAL milik shift — bukan dipercaya dari input, pola TOCTOU sama `FinalizeStockOpnameAction`; begitu final, `HasDocumentState` mengunci field kas — "tidak dapat disesuaikan" ditegakkan struktural) + `VoidCashierShiftAction` + Filament resource (`SaleResource`/`CashierShiftResource`, grup navigasi "Penjualan") + tests. **Batas cakupan T4.1 (didokumentasikan, bukan kelalaian):** pembayaran WAJIB LUNAS saat finalisasi (jumlah `sale_payments` harus persis sama dengan `total_amount`) — DP/piutang parsial dan penegakan ambang diskon TH1/TH2 ditunda ke T4.2. **Dua bug ditemukan saat verifikasi:** (1) `UserFactory` dasar tidak pernah mengisi `default_branch_id` (NOT NULL) — lolos tanpa terdeteksi sepanjang Fase 1-3 karena seluruh test sebelumnya memakai helper `makeTestUser()`, baru tersingkap saat `CashierShiftFactory` merantai `User::factory()` murni; diperbaiki dengan menambah `Branch::factory()` sebagai default di `UserFactory`. (2) CHECK constraint awal `discount_amount <= subtotal` pada migration `sales` salah — `subtotal` sengaja bernilai 0 sepanjang draft (baru dihitung `FinalizeSaleAction`), sehingga menolak alur normal "isi diskon sebelum finalisasi"; constraint itu dihapus dari migration, validasi total negatif dipindah sepenuhnya ke `FinalizeSaleAction`. Full test suite 375 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T4.2 | ✅ Selesai | TH1/TH2 (diskon) + DP/piutang parsial. `FinalizeSaleAction` dipecah `execute()`/`applyAndFinalize()` (pola `FinalizeStockAdjustmentAction`) — ambang dipilih dari PERAN aktor (Kasir → `discount.max_kasir`/TH1, Admin → `discount.max_admin`/TH2, Owner dikecualikan), melebihi ambang membuat `Approval` tertunda (AP-01, dokumen tetap draft, TANPA nomor dokumen/sentuhan ledger) alih-alih menolak transaksi. `ApproveSaleDiscountAction` (permission `manage_sale_discount`, terpisah dari `decide_approval` generik — pola `approve_stock_adjustment`) menyetujui lalu langsung `applyAndFinalize()` di transaksi yang sama. DP: kolom baru `sales.amount_paid`/`balance_due`/`payment_status` (migration tambahan, D2 — bukan mengubah migration `sales` lama) dikunci saat finalisasi; pembayaran BOLEH kurang dari total HANYA bila `partner_id` terisi (walk-in tidak boleh punya piutang — tidak ada yang bisa ditagih); melebihi total tetap ditolak (kembalian tunai adalah urusan POS UI T4.4, bukan `sale_payments`). **TIDAK ADA tabel `receivables` terpisah** — piutang penuh (jatuh tempo, aging) sengaja ditunda ke Fase 5 ("Kas + piutang", §3), saldo untuk MVP cukup dilacak di dokumen `Sale` itu sendiri. **Gotcha operasional ditemukan saat verifikasi (bukan bug kode, tapi jebakan proses):** `php artisan migrate --env=testing` TIDAK bisa diandalkan menyasar `rightclick_testing` — tidak ada berkas `.env.testing`, jadi flag `--env=` pada `artisan migrate` biasa (BUKAN `php artisan test`, yang benar lewat `phpunit.xml` + `tests/bootstrap.php` T1.11) tidak mengubah `DB_DATABASE` sama sekali dan diam-diam menulis ke `rightclick` (dev) — tanpa error, migration terlihat "berhasil". Migrasi manual ke database test WAJIB pakai override eksplisit: `docker compose exec -T -e DB_DATABASE=rightclick_testing app php artisan migrate --force`. Full test suite 388 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T4.3 | ✅ Selesai | Retur penjualan (AC-18: "Barang diretur → kembali pada nilai perolehan batch asal, bukan harga jual"). `sale_returns`/`sale_return_lines` + `DocumentType::SaleReturn` → `RET` (self-derived) + model/Policy (`SaleReturnLinePolicy` tanpa API tulis independen) + `FinalizeSaleReturnAction` + `VoidSaleReturnAction` + `SaleReturnResource` (grup navigasi "Penjualan"). Baris retur merujuk `sale_item_id` (BUKAN `product_id` langsung) — `unit_cost` yang dikirim ke `StockLedgerService::receive()` diambil dari `sale_items.unit_cost_snapshot` (HPP asli), SEDANGKAN `unit_price`×qty = `refund_amount` (catatan nilai kembali ke pelanggan) diambil dari `sale_items.unit_price` (harga jual) — DUA nilai berbeda dari SATU baris sumber, inti AC-18. Kontrol anti-fraud retail: `create_sale_return` (Kasir boleh mengajukan draft) terpisah dari `process_sale_return` (HANYA Admin/Owner yang bisa mencairkan ke ledger — Kasir SENGAJA tidak punya permission ini). Kuantitas retur divalidasi terhadap SISA yang belum diretur, terakumulasi LINTAS dokumen retur sebelumnya (bukan hanya per dokumen). R3/T3.7: retur adalah sisi "naik" (barang masuk kembali) — BEDA dari `sale_items` (sisi konsumsi), serial number WAJIB divalidasi untuk produk serial. **TIDAK ADA pencatatan kas keluar sungguhan** (`cash_entries` Fase 5) — `total_refund` murni catatan nilai, sama batas cakupan dengan DP T4.2. **Bug Filament ditemukan saat verifikasi (jebakan reaktivitas, bukan bug logika bisnis):** `Get('../sale_id')` di dalam closure `options()` sebuah `Select` bersarang dalam `Repeater` mengembalikan `null` TANPA ERROR (lolos render, gagal validasi "in:" saat submit) — SATU level `../` hanya keluar dari skema ITEM Repeater, BUKAN dari Repeater itu sendiri; field header sepadan Repeater (bukan sepadan BARIS) perlu DUA level `../../sale_id`. Beda dari pola `$get('direction')` (field sepadan DALAM baris yang sama) yang sudah dipakai `StockAdjustmentForm` — jangan disamakan. Full test suite 402 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T4.4 | ✅ Selesai | POS Livewire mandiri (`App\Presentation\Pos`, sudah diantisipasi sejak T1.10/`AdminPanelProvider`/`filament-theme.css` — "POS adalah Livewire terpisah"). `PosTerminal` (route `/pos`, guard sesi 'web' SAMA dengan login Filament — tidak ada sistem login kedua) + `ShowSaleReceiptController` (nota, route `/pos/sales/{sale}/receipt`, GET murni/idempoten). Katalog produk+jasa, keranjang, buka shift langsung dari POS (tutup shift tetap lewat Filament `CashierShiftResource` T4.1 — tidak diduplikasi), diskon, multi-pembayaran, checkout memanggil `FinalizeSaleAction` yang sama dipakai `SaleResource` — SATU jalur bisnis, dua permukaan UI. POS-05 (`products()` tidak pernah memfilter stok nol, badge "HABIS" di view) dan POS-06 (`products()`/`services()` hanya `select()` kolom aman — TIDAK PERNAH JOIN ke `stock_batches`/`stock_mutations`, satu-satunya tempat `unit_cost` hidup — P6 di lapisan query) diverifikasi eksplisit di test. AC-14/R13: nota (`pos.receipt` view) TIDAK PERNAH menulis baris/perhitungan PPN sama sekali (bukan disembunyikan kondisional) — diverifikasi test memindai isi HTML tidak mengandung kata "PPN"/"pajak"/"VAT". NT-05: nota bisa diakses berulang (cetak ulang) tanpa efek samping (controller GET murni); pesan "transaksi tersimpan, cetak ulang bila gagal" statis di UI, bukan deteksi kegagalan `window.print()` (tidak feasible diuji headless, dan tidak diperlukan — pesannya benar terlepas hasil cetak). U8/R8: checkout HANYA transaksi database LOKAL (tidak ada panggilan HQ/eksternal sinkron di jalur ini) — banner `NodeConnectivityService` (dipakai ulang dari halaman login T1.11) murni informatif, TIDAK PERNAH menonaktifkan tombol Bayar. PV7: `resources/css/pos-theme.css` (entry Vite baru, terdaftar `vite.config.js`) menegakkan `transition: none !important` global pada `.pos-root`, bukan sekadar menghindari kelas `transition-*` di Blade. **Gotcha lingkungan ditemukan saat verifikasi:** container `app` TIDAK punya node/npm — `@vite(['resources/css/pos-theme.css'])` melempar `ViteManifestNotFoundException` sampai `npm run build` dijalankan dari HOST (bukan Docker) untuk memasukkan entry baru ke `public/build/manifest.json`; setiap entry Vite baru WAJIB rebuild manual dengan cara yang sama sebelum test yang merender view terkait bisa lulus. Browser pane MCP tetap tidak bisa menjangkau container di sandbox ini (limitasi sama yang didokumentasikan T2.9, dikonfirmasi ulang: `curl --resolve rightclick.localhost:8443:127.0.0.1` berhasil 200/32.849 byte, tapi navigasi Browser pane ke host manapun selain `localhost` polos ditolak) — diganti verifikasi Livewire component test (12 test: mount/auth gate, buka shift, POS-05, cart, checkout sukses/gagal-stok/pending-approval) + test HTTP nyata untuk rute nota (bukan `Livewire::test()`, yang diam-diam MELEWATI middleware untuk render awal — ditemukan T4.3). Full test suite 414 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |

| T4.5 | ✅ Selesai | Architecture test data-driven baru (3 resource Sales & POS — `SaleResource`/`CashierShiftResource`/`SaleReturnResource`, pola sama T2.10/T3.8) menyusul `SaleItem`/`SalePayment`/`SaleReturnLine` yang SENGAJA tidak terdaftar (baris anak tanpa API tulis independen, pola `StockAdjustmentLine`) dan POS (`App\Presentation\Pos`, T4.4) yang SENGAJA di luar cakupan (bukan `Filament\Resources\Resource` — halaman Livewire mandiri). Polish: item navigasi "Buka POS" di sidebar Filament (`AdminPanelProvider`, grup "Penjualan") menautkan balik ke `/pos` — simetris dengan tautan "Kembali ke Back Office" yang sudah ada di `pos.terminal` view sejak T4.4 — digerbang permission `create_sale` YANG SAMA dipakai `PosTerminal::mount()` sendiri, supaya tautan yang tampak di sidebar tidak pernah membawa pengguna ke halaman 403. Docblock `SaleResource` diperbarui menyebut Action `approve` (T4.2) yang sebelumnya tidak disebut. Full test suite 419 pass (1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |

**Fase 4 (Sales & POS) SELESAI** — T4.1–T4.5 lengkap. Fase berikutnya: **Fase 5 — Procurement + Kas + Sinkronisasi** (PO, penerimaan, faktur, hutang, kas, piutang, outbox, sync API — lihat dua simpul kritis T5.7 outbox-dalam-transaksi-yang-sama dan T5.2 unit_cost-termasuk-PPN di §11 atas). PT1/PT2/PT5/PT7/PT13/PT14 (deferred sejak T1.13 ke modul Sales) SEKARANG bisa ditutup — Sale/CashierShift/SaleReturn sudah ada; belum ditutup eksplisit di sesi ini, tersisa sebagai pekerjaan test tambahan yang bisa diambil kapan pun (tidak memblokir Fase 5).

### Tiga simpul yang tidak boleh dilewati (lanjutan §11)

| Simpul | Alasan |
|---|---|
| ~~**T4.2** — TH1/TH2 (diskon) ditegakkan pada TOTAL, bukan per baris~~ | ✅ Ditegakkan T4.2 — §10 eksplisit; tanpanya diskon tinggal dipecah per baris untuk lolos approval — sama pola TH3/TH3b yang sudah ditegakkan T3.5. `discount_amount` tetap satu kolom di level dokumen `Sale` (bukan per `sale_items`), jadi struktur data sendiri sudah mencegah pemecahan per baris |
| ~~**T4.3** — AC-18: retur kembali pada nilai HPP batch asal, bukan harga jual~~ | ✅ Ditegakkan T4.3 — `FinalizeSaleReturnAction` mengambil `unit_cost` dari `sale_items.unit_cost_snapshot` (bukan `unit_price`) untuk `StockLedgerService::receive()` |

### Fase 5 — Procurement + Kas + Sinkronisasi

> **Peringatan penomoran (sama seperti Fase 2/3/4):** `HS-TASKS-RIGHTCLICK-v1.1`/`HS-DB-RIGHTCLICK-v1.0` tetap tidak ada di repositori (dikonfirmasi ulang sebelum menyusun daftar ini). T5.1–T5.10 di bawah diturunkan sendiri dari §3/§11 dokumen ini ("PO, penerimaan, faktur, hutang, kas, piutang, outbox, sync API" + dua simpul kritis T5.2/T5.7 yang sudah ditandai sejak §11 versi sebelumnya), sama seperti T2.x–T4.x. Kode prefix dokumen baru (PO, penerimaan, faktur) dan desain tabel hutang/piutang **belum diverifikasi terhadap dokumen sumber** — akan self-derived saat task terkait dikerjakan, dicatat eksplisit di migration/enum seperti pola fase-fase sebelumnya. Dua ketidakpastian desain diketahui SEBELUM mulai coding (bukan baru ditemukan saat implementasi): (1) ~~T5.2 mungkin perlu digabung jadi satu task~~ — RESOLVED di T5.2: tetap dua tabel terpisah (`goods_receipts`/`purchase_invoices`, sesuai §7), tapi HANYA `GoodsReceipt` yang menyentuh ledger (`purchase_invoices` header-only, tanpa lines sendiri, menaut 1:1 SETELAH stok bergerak) — lihat catatan T5.2 di bawah; (2) T5.5 (piutang penuh) desain tabelnya belum jelas dari CLAUDE.md — T4.2 hanya menyebut piutang parsial ditunda ke sini, tanpa merinci bentuk tabelnya.

| ID | Status | Catatan |
|---|---|---|
| T5.1 | ✅ Selesai | `purchase_orders` (SYNCED, branch-scoped) + `purchase_order_lines` (`unit_price` — harga PESANAN, BUKAN `unit_cost` batch; nilai batch sebenarnya termasuk PPN baru ditentukan T5.2) + `PurchaseOrderPolicy`/`PurchaseOrderLinePolicy` + `FinalizePurchaseOrderAction`/`ApprovePurchaseOrderAction`/`VoidPurchaseOrderAction` (pola persis `FinalizeStockAdjustmentAction`/`FinalizeSaleAction` — TH4 dari `settings.po.max_admin` yang SUDAH diseed T1.9, Owner dikecualikan, AP-01 lewat `ApprovalService`). PO TIDAK menyentuh `StockLedgerService` sama sekali — murni rencana pembelian, stok baru bergerak saat goods receipt (T5.2). Prefix dokumen `PO` (self-derived, dua huruf — beda pola dari ADJ/OPN/dst, ditandai untuk direkonsiliasi). **Gap permission ditemukan (pola sama `view_stock_cost` T3.3):** 6 permission Procurement yang diseed T1.5 tidak punya permission dedicated untuk void PO final — `edit_purchase_order` hanya menggerbang draft. Ditambahkan `void_purchase_order` (permission ke-69), Procurement jadi 7. Validasi tambahan di `FinalizePurchaseOrderAction`: pemasok bertipe `PartnerType::Customer` murni ditolak (PO harus ke `Supplier`/`Both`). Filament Resource BELUM dibangun (cakupan T5.6, pola T3.1/T3.2 vs T3.3). Full test suite 437 pass (1 skip tak terkait, +18 test baru), PHPStan level 6 bersih (perlu tambahan `@property PartnerType $partner_type` pada model `Partner` — sebelumnya tanpa docblock sama sekali sehingga Larastan tidak mengenali cast enum-nya saat dibandingkan strict di `FinalizePurchaseOrderAction`), Pint bersih |
| T5.2 | ✅ Selesai | **Simpul kritis.** `goods_receipts`+`goods_receipt_lines` (SYNCED) + `purchase_invoices` (SYNCED, header-only — TANPA `purchase_invoice_lines` sendiri, deviasi dari rencana awal T5.1: rincian produk sudah lengkap di `goods_receipt_lines`, mendata ulang di sini tidak menambah nilai untuk kebutuhan AP T5.3 yang hanya perlu total). **`GoodsReceipt`, BUKAN `PurchaseInvoice`, yang memanggil `StockLedgerService::receive()`** — `goods_receipt_lines.unit_cost` WAJIB TERMASUK PPN (R2/AC-09), diketik apa adanya dari faktur pemasok yang menyertai barang fisik (HAEN KOMPUTER non-PKP — tidak ada DPP yang perlu dipisah). `purchase_invoices` adalah catatan hutang/AP FORMAL yang menaut 1:1 (`unique(goods_receipt_id)`, pola `stock_transfer_receipts` T3.6) SETELAH stok sudah bergerak — `total_amount`-nya DIKUNCI sama dengan `goods_receipts.total_amount` saat `FinalizePurchaseInvoiceAction`, bukan pemicu ledger kedua. `FinalizeGoodsReceiptAction`/`VoidGoodsReceiptAction`/`FinalizePurchaseInvoiceAction`/`VoidPurchaseInvoiceAction` — TIDAK ADA alur `ApprovalService` di keduanya (§10 tidak menetapkan TH untuk goods receipt maupun faktur pembelian, beda dari PO/TH4). Void GoodsReceipt ditolak selama faktur aktif menaut (pola `VoidStockTransferAction` dispatch-vs-receipt, T3.6) — cegah faktur mengklaim hutang atas stok yang sudah dibalik. **Disambiguasi permission (bukan permission baru):** 4 permission goods-receipt yang diseed T1.5 ternyata tumpang tindih nama di dua domain (`perform_goods_receipt`/`review_goods_receipt` Inventory vs `view_goods_receipt`/`approve_goods_receipt` Procurement) untuk SATU konsep dunia nyata — dipecah eksplisit: `perform_goods_receipt`=create/finalize GoodsReceipt (Gudang, tanpa ambang), `review_goods_receipt`=void GoodsReceipt (Admin/Owner meninjau sisi fisik), `view_goods_receipt`=baca KEDUA dokumen, `approve_goods_receipt`=seluruh aksi tulis PurchaseInvoice (Admin/Owner sisi finansial/AP). Detail penuh di docblock `PermissionSeeder`. Prefix dokumen self-derived: `GoodsReceipt`→`PB` (gaya Indonesia, KEMBALI ke pola ADJ/OPN — sengaja beda dari `PO` T5.1), `PurchaseInvoice`→`INV` (sengaja BUKAN `FP` untuk hindari rancu dengan "Faktur Pajak"). Full test suite 455 pass (+18, 1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T5.3 | ✅ Selesai | `purchase_payments` (cicilan/parsial atas `purchase_invoices`) + `RecordPurchasePaymentAction`. **Keputusan desain kunci:** `purchase_invoices` SENGAJA TIDAK diberi kolom `amount_paid`/`balance_due`/`payment_status` tersimpan (beda dari `sales`, T4.2) — cicilan ditulis DARI WAKTU KE WAKTU setelah faktur `final`, dan `HasDocumentState` menolak perubahan field apa pun pada dokumen final selain transisi void, jadi kolom semacam itu TIDAK BISA diperbarui pasca-finalisasi. Saldo hutang DIHITUNG dari `purchase_payments.amount` SUM vs `total_amount` — pola `stock_balances`-sebagai-cache-turunan (R1) diterapkan ke konteks finansial (`PurchaseInvoice::amountPaid()`/`balanceDue()`/`paymentStatus()`, plus `outstandingBalanceForPartner()` sebagai primitif "saldo hutang per partner" — laporan penuh tetap T5.6). `PurchasePaymentPolicy::create()` BEDA dari `SalePaymentPolicy` (yang selalu `false`) — digerbang `record_cash_entry` sungguhan karena `PurchasePayment` ditulis langsung lewat action, bukan byproduct finalisasi dokumen induk (tidak ada permission baru ditambahkan, `record_cash_entry`/`view_payables` sudah diseed T1.5 untuk kebutuhan Fase 5). `VoidPurchaseInvoiceAction` (T5.2) diperkuat: menolak void bila sudah ada `purchase_payments` tercatat (payment immutable, tanpa mekanisme koreksi individual — dibatasi sengaja, bukan kelalaian). `PaymentMethod`/`PaymentStatus` dipakai ulang langsung dari `App\Domain\Sales\Enums` (BUKAN diduplikasi ke `Domain\Procurement`) — didokumentasikan sebagai kandidat promosi ke `Domain\Shared` di kemudian hari (≈10 berkas Sales akan ikut berubah, di luar cakupan T5.3, ditandai bukan diam-diam diabaikan). TIDAK menulis `cash_entries` (T5.4 belum ada) — `RecordPurchasePaymentAction` kemungkinan besar akan di-retrofit T5.4 untuk juga mencatat kas keluar saat `method=cash`, pola sama retrofit `outbox_events` (T5.7) ke seluruh action finalize SYNCED. Full test suite 465 pass (+10, 1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T5.4 | ✅ Selesai | `cash_entries` (SYNCED, append-only, pola persis `stock_mutations`) + `CashLedgerService` — **satu-satunya penulis** (`CashEntrySingleWriterTest`, pola pemindaian berkas sama `StockMutationSingleWriterTest`/R1). `amount` BERTANDA (positif=masuk, negatif=keluar) — bukan kolom `direction` terpisah, sama konvensi `stock_mutations.quantity`. `reference_type`/`reference_id` SELALU menunjuk DOKUMEN INDUK (`Sale`/`PurchaseInvoice`), bukan baris pembayaran individual — konsisten dengan `stock_mutations` yang juga menunjuk dokumen, bukan baris. AC-21 (kas keluar tanpa referensi ditolak) ditegakkan berlapis: parameter `Model $reference` non-nullable di `CashLedgerService::record()` (tipe PHP) + kolom NOT NULL di database (diuji eksplisit lewat raw insert yang sengaja melanggar). **Retrofit dua action yang sudah ada** (pola sama yang diantisipasi sejak catatan T5.3): `FinalizeSaleAction` menerbitkan `CashEntry` masuk untuk tiap `sale_payments.method='cash'`; `VoidSaleAction` memanggil `CashLedgerService::reverseForReference()` (baru, mengikuti pola `StockLedgerService::reverseForReference()`) supaya pembatalan penjualan tunai tidak meninggalkan kas masuk hantu; `RecordPurchasePaymentAction` menerbitkan `CashEntry` keluar (negatif) untuk `method='cash'`. **Keputusan desain:** TIDAK ada modul "kas keluar bebas/manual" di MVP ini — akuntansi penuh eksplisit di luar cakupan (§3), jadi `cash_entries` HANYA bisa lahir dari dua jalur otomatis di atas, tanpa Filament/Livewire form input bebas yang bisa memicu AC-21 secara nyata dari UI (T5.6 nanti, bila ada, tetap wajib mengikuti kontrak `CashLedgerService` yang sama). `CloseCashierShiftAction` (T4.1) SENGAJA TIDAK diubah — tetap menghitung `closing_cash_expected` langsung dari `sale_payments` (bukan dari `cash_entries`), sumber paralel yang identik secara matematis tapi belum dikonsolidasikan; ditandai sebagai kerapian arsitektur masa depan, bukan bug. Full test suite 479 pass (+14, 1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T5.5 | ✅ Selesai | `receivable_payments` (cicilan/parsial atas sisa `sales.balance_due`) + `RecordReceivablePaymentAction`. **Keputusan desain: pola PERSIS T5.3 (Hutang), diterapkan simetris ke sisi AR.** `sales.balance_due` (dikunci T4.2 saat finalize — piutang AWAL saat transaksi) TETAP TIDAK PERNAH diperbarui langsung (R4/`HasDocumentState`); sisa piutang KINI dihitung dari `balance_due` dikurangi SUM `receivable_payments` (`Sale::amountCollected()`/`remainingReceivable()`/`receivableStatus()` — BEDA dari `payment_status` tersimpan T4.2 yang tetap merepresentasikan status DP historis saat finalize, bukan status kini). `outstandingReceivableForPartner()` sebagai primitif "saldo piutang per partner", pola persis `PurchaseInvoice::outstandingBalanceForPartner()`. `ReceivablePaymentPolicy::create()` digerbang `record_cash_entry` (bukan `view_sales`/selalu `false`) — sama pola `PurchasePaymentPolicy`; `viewAny`/`view` digerbang `view_receivables` (kolom §10 yang tepat). `CashEntryType::ReceivableCollection` (kasus enum baru, BEDA dari `SalePayment`) — uang masuk BELAKANGAN via pelunasan dibedakan dari uang masuk DI MUKA saat transaksi, untuk kebutuhan laporan. `VoidSaleAction` (T4.1/T5.4) mendapat guard baru: menolak void bila sudah ada `receivable_payments` tercatat — pola persis guard `VoidPurchaseInvoiceAction` (T5.3). `PaymentMethod`/`PaymentStatus` dipakai ulang dari `Domain\Sales\Enums` — di sini justru domain aslinya (Sales), tidak ada isu lintas-domain seperti `RecordPurchasePaymentAction`. Full test suite 491 pass (+12, 1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T5.6 | ✅ Selesai | 4 Filament Resource baru: `PurchaseOrderResource` (finalize/approve/void — satu-satunya dengan alur TH4/`hasPendingApproval()`, pola persis `StockAdjustmentResource`), `GoodsReceiptResource` (finalize/void, TANPA approve — §10 tidak menetapkan TH), `PurchaseInvoiceResource` (finalize/void + Action baru `pay` "Catat Pembayaran" yang memanggil `RecordPurchasePaymentAction`, TETAP tersedia berkali-kali selama `state=final` dan sisa hutang > 0 — beda dari finalize/void yang transisi sekali jalan), `CashEntryResource` (List+View SAJA, pola persis `StockMutationResource` — append-only, tanpa form). Grup navigasi baru "Procurement" (PO/Penerimaan/Faktur) dan "Kas" (CashEntry) — cukup deklarasi `$navigationGroup` string per Resource, TIDAK ada registrasi terpusat di `AdminPanelProvider` (dikonfirmasi: seluruh grup lain di codebase juga begitu). Piutang (T5.5) TIDAK dapat Resource baru — ditambahkan sebagai Action baru `collectReceivable` "Catat Pelunasan" pada `SalesTable` yang SUDAH ADA (T4.1/T4.5), memanggil `RecordReceivablePaymentAction`, visible hanya saat `remainingReceivable() > 0`. Baris anak (`PurchaseOrderLine`/`GoodsReceiptLine`) TIDAK dapat Resource sendiri — nested `Repeater::make('lines')->relationship('lines')` di form induk, pola SATU-SATUNYA yang dipakai codebase ini untuk data anak (dikonfirmasi: TIDAK ADA RelationManager di mana pun di seluruh `app/Presentation/Filament`). **Dua gotcha ditemukan saat verifikasi (bukan bug logika bisnis, jebakan lingkungan/testing):** (1) tiga Resource create-form test awalnya gagal dengan `Call to a member function getDefaultTestingSchemaName() on null` — root cause SEBENARNYA (setelah tracing lewat `storage/logs/laravel.log`) adalah `vendor/composer/autoload_classmap.php` yang sudah dioptimasi (`composer install --optimize-autoloader`, image produksi-style) tidak otomatis mengenali file class BARU yang ditambahkan sesi ini — `Error: Class ... not found` untuk halaman Filament yang baru dibuat, sampai `composer dump-autoload` dijalankan ulang (yang juga memicu hook `filament:upgrade` di `composer.json`, membersihkan cache config/route/view sekaligus) — WAJIB dijalankan setiap kali menambah file class baru di container yang classmap-nya sudah dioptimasi, bukan hanya migration; (2) setelah itu, 3 test yang sama TETAP gagal dengan error identik tapi sebab BERBEDA — ternyata `HttpException` 403 tertangkap oleh `Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess` (dilacak lewat reflection ke `Testable::lastState` karena Livewire menelan exception-nya tanpa mencatat ke log) — halaman Create Filament mensyaratkan permission `viewAny` (bukan hanya `create`) untuk mount, persis pola yang SEBENARNYA sudah dipakai `StockAdjustmentResourceTest` (`['perform_adjustment', 'view_stock_mutations']`, DUA permission) tapi terlewat disalin ke tiga test baru (hanya diberi permission `create`/`perform` tunggal) — diperbaiki dengan menambahkan permission `view_*` yang sesuai ke `makeTestUser()` di ketiga test. Full test suite 502 pass (+11, 1 skip tak terkait), PHPStan level 6 bersih, Pint bersih |
| T5.7 | ⬜ Belum mulai | **Simpul kritis.** `outbox_events` (LOCAL) + penulisan dalam TRANSAKSI YANG SAMA dengan tiap dokumen final SYNCED. Di luar transaksi = dokumen final hilang tanpa jejak ke HQ. Kemungkinan besar butuh RETROFIT ke action finalize yang sudah ada (`FinalizeSaleAction`, `FinalizeStockAdjustmentAction`, `FinalizeStockOpnameAction`, dst dari Fase 3–4), bukan cuma task baru berdiri sendiri |
| T5.8 | ⬜ Belum mulai | Sync API — 6 endpoint §8 (`/sync/events`, `/ack`, `/health`, `/master-check`, `/master-snapshot/{table}`, `/partner-upsert`), token per node via VPN, 4 status hasil (`accepted`/`duplicate`/`deferred`/`rejected`), `processed_events` idempotency key. Worker dibatasi 1 proses (B7/H1, i3-7100 2 core) — relevan untuk desain consumer sync |
| T5.9 | ⬜ Belum mulai | Menutup PT1/PT2/PT5/PT7/PT13/PT14 (otorisasi negatif, ditunda sejak T1.13 → Sales) — sekarang bisa ditutup karena Sale/CashierShift/SaleReturn sudah ada sejak Fase 4 |
| T5.10 | ⬜ Belum mulai | Architecture test data-driven (resource Procurement/Kas, pola T2.10/T3.8/T4.5) + full test suite + PHPStan level 6 + Pint — penutup fase |

---

## 12. Acceptance Criteria — Kunci

Format Given/When/Then. Daftar lengkap di `HS-PRD-RIGHTCLICK-v1.0` bagian 10.

| ID | Kriteria |
|---|---|
| **AC-08** | Dua batch berbeda harga → HPP diambil dari batch tertua lebih dulu |
| **AC-09** | Faktur memuat PPN → nilai batch = nilai faktur **termasuk** PPN |
| **AC-10** | Dua kasir menjual unit terakhir bersamaan → satu berhasil, satu ditolak, stok tidak negatif |
| **AC-11** | Dokumen kirim final, belum diterima → barang transit, tidak dapat dijual di kedua cabang |
| **AC-12** | Baris opname berselisih tanpa alasan → penyimpanan ditolak |
| **AC-13** | Internet putus, server lokal menyala → transaksi tersimpan, stok berkurang, nota tercetak |
| **AC-14** | Nota dicetak → tidak ada baris atau perhitungan PPN dalam bentuk apa pun |
| **AC-16** | Selisih shift → tercatat sebagai dokumen tersendiri, tidak dapat disesuaikan |
| **AC-18** | Barang diretur → kembali pada nilai perolehan batch asal, bukan harga jual |
| **AC-21** | Kas keluar tanpa referensi dokumen → penyimpanan ditolak |

---

## 13. Testing Requirements

| Kelompok | Cakupan | Sumber |
|---|---|---|
| **PT1–PT16** | Otorisasi negatif — memastikan yang tidak boleh benar-benar ditolak. T1.13: 7/16 diuji generik (`tests/Feature/AuthorizationScenariosTest.php`), 9 ditunda ke fase modulnya | `HS-PERM-RIGHTCLICK-v1.2` |
| **UT1–UT20** | Antarmuka — offline, printer, kontras, font lokal, pintasan | `HS-UI-RIGHTCLICK-v1.1` |
| **T1–T12** | Sinkronisasi — idempotensi, deferred, offline 72 jam, duplikat partner | `HS-API-RIGHTCLICK-v1.0` |
| Architecture test | Model tanpa Policy; penulis `stock_mutations` selain `StockLedgerService` | `HS-TASKS-RIGHTCLICK-v1.0` |
| Uji beban | 20.000 SKU, 500.000 mutasi, pada i3-7100 | T3.11 |

**Setiap acceptance criterion harus terpetakan ke minimal satu test.**

---

## 14. Deployment Requirements

### Docker Compose per node

`app` (PHP-FPM 8.3) · `web` (Caddy) · `db` (PostgreSQL 16) · `redis` · `worker` · `scheduler` · `backup`

### Perangkat keras per node cabang

Intel Core i3-7100 · RAM 16 GB DDR4 · SSD 512 GB · UPS 1200 VA

> **Catatan:** i3-7100 memiliki 2 core. Batasi `worker` queue ke **1 proses** dan pantau load average pada bulan pertama. Node Cabang Utama menanggung beban ganda (cabang + HQ) — jadwalkan tugas berat HQ di luar jam operasional.

### Aturan deploy

| # | Aturan |
|---|---|
| D1 | **HQ diperbarui lebih dulu**, lalu node cabang |
| D2 | Migration harus **kompatibel mundur dalam satu rilis** — tambah kolom, jangan hapus |
| D3 | Deploy manual dan disengaja per node. **Tidak boleh pada jam operasional** |
| D4 | Snapshot database sebelum setiap migration produksi |
| D5 | Tidak ada perubahan skema manual di produksi |
| D6 | Rollback: tag image sebelumnya + restore snapshot |

### Backup

`pg_dump` harian per node, terenkripsi sebelum meninggalkan server, off-site, retensi 30 hari / 12 minggu / 12 bulan, uji restore bulanan.

**Backup wajib aktif dan terverifikasi sebelum Fase 1 dinyatakan selesai.**

**Implementasi (T1.12):** service `backup` di `docker-compose.yml`, image `postgres:16-alpine` (pg_dump/pg_restore selalu sama versi dengan server) + gnupg + rclone. Skrip: `docker/backup/backup.sh` (dump → enkripsi AES256 simetris → salin weekly/monthly → retensi berbasis jumlah berkas → sinkronisasi off-site via rclone), `docker/backup/restore-test.sh` (dekripsi dump terbaru → restore ke database sementara → verifikasi tabel inti ada → hapus database sementara). Dijadwalkan via cron di dalam container (`BACKUP_SCHEDULE_DAILY`, default 02:00 **Asia/Jakarta** — bukan UTC, wajib di luar jam operasional). Enkripsi memakai passphrase simetris (`BACKUP_ENCRYPTION_PASSPHRASE`), bukan keypair asimetris — trade-off disengaja agar node tak berawak dapat menjalankan uji restore bulanan otomatis tanpa kunci privat off-site. Kredensial off-site (`docker/backup/rclone.conf`, digitignore) terpisah dari kredensial database; `BACKUP_OFFSITE_REMOTE` kosong = backup tetap jalan tapi hanya lokal, dengan peringatan di log. Diverifikasi manual end-to-end (dump → enkripsi → dekripsi → restore → sanity check tabel inti) terhadap database dev sungguhan sebelum commit.

---

## 15. Prompt Standar Sesi Claude Code

```
You are the Lead Software Engineer of HAEN SOFTWARE.

Implement RIGHTCLICK according to the approved documentation in CLAUDE.md.

Follow exactly: PRD, System Architecture, Database Design, API Specification,
UI Requirements, Permission Matrix, and Acceptance Criteria.

Do not modify business requirements without approval.
If documentation is incomplete, identify the missing information before writing code.

Work task by task from HS-TASKS-RIGHTCLICK-v1.0, in order. For every task:
1. Explain the objective.
2. List affected files.
3. Generate production-ready code.
4. Explain implementation.
5. Provide testing instructions.
6. Wait for approval before continuing.

Follow HAEN_ENGINEERING_STANDARD_v1.1 principles: Clean Architecture,
Modular Design, Secure Coding, Responsive UI, Production Ready —
adapted to the approved Laravel + Filament stack deviation.
```

---

## 16. Hal yang Sering Terlewat — Baca Sebelum Menulis Kode

| # | Peringatan |
|---|---|
| 1 | **`unit_cost` batch termasuk PPN.** Jangan mengambil nilai DPP dari faktur. Ini akan membuat seluruh HPP terlalu rendah dan margin terlihat lebih bagus dari kenyataan |
| 2 | **Jangan pernah menulis `stock_mutations` di luar `StockLedgerService`.** Ada architecture test yang akan gagal |
| 3 | **`outbox_events` ditulis dalam transaksi yang sama dengan dokumen.** Di luar transaksi = dokumen final yang hilang tanpa jejak |
| 4 | **Tidak ada PPN di penjualan.** Jangan menambahkan kolom, field, atau baris pajak di mana pun |
| 5 | **Produk tidak punya kolom harga beli.** Harga perolehan ada di batch |
| 6 | **`audit_logs` dan `stock_mutations` tanpa soft delete dan tanpa `updated_at`** — keduanya append-only |
| 7 | **Font Inter dimuat lokal**, bukan dari Google Fonts CDN |
| 8 | **Teks cyan di atas putih dilarang** untuk teks isi — kontras hanya 2,6:1 |
| 9 | **Void tidak menghapus mutasi lama** — terbitkan mutasi berlawanan yang merujuk dokumen void |
| 10 | **Kolom sensitif disaring di query**, bukan disembunyikan di tampilan |

---

## 17. Referensi Dokumen

Seluruhnya di `FOUNDER MODE/04_PROJECTS/ACTIVE/HS-RIGHTCLICK/01_DOCS/`

| Dokumen | Versi | Status |
|---|---|---|
| Memo Deviasi Stack | 1.0 | Final |
| Module Architecture | 1.1 | Final |
| Chart of Accounts | 1.1 | Final |
| PRD | 1.0 | Final |
| System Architecture | 1.1 | Final |
| Database Design | 1.0 | Final |
| API Specification | 1.0 | Final |
| Permission Matrix | 1.2 | Draft — menunggu approval COO (revisi penamaan dari v1.1 Final, lihat HS-PERM-RIGHTCLICK-v1.2 §"Mengapa Versi Ini Ada") |
| UI Requirements | 1.1 | Final |
| Development Tasks | 1.1 | Final |
| Brand Identity Guidelines | 1.0 | Final |

**Bila dokumen dan kode berbeda, dokumen yang berlaku.** Perubahan requirement hanya melalui versi dokumen baru yang disetujui — bukan melalui perubahan kode.

---

## 18. Menjalankan Proyek (Development)

Ditambahkan pada T1.1. Seluruh perkakas berjalan **di dalam container** — PHP, Composer, dan PostgreSQL tidak perlu terpasang di mesin pengembang.

```
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Antarmuka Filament: `https://rightclick.localhost:8443/admin` (sertifikat CA internal Caddy, S2).

| Tujuan | Perintah |
|---|---|
| Test suite | `docker compose exec app php artisan test` |
| PHPStan level 6 | `docker compose exec app vendor/bin/phpstan analyse` |
| Format kode | `docker compose exec app vendor/bin/pint` |
| Seluruh gate DoD | `docker compose exec app composer check` |

### Ketentuan yang ditetapkan pada T1.1

| # | Ketentuan | Alasan |
|---|---|---|
| B1 | Model Eloquent berada di `app/Infrastructure/Persistence/Models`. **`app/Models` tidak dihidupkan kembali** | Aturan lapisan HS-ARCH 2.1; ditegakkan `tests/Arch/LayeringTest.php` |
| B2 | Panel Filament berada di `App\Presentation\Filament`, discovery menunjuk `app/Presentation/Filament/{Resources,Pages,Widgets}` | Sama |
| B3 | Test suite berjalan di atas **PostgreSQL** (`rightclick_testing`), bukan SQLite | Skema bergantung pada CHECK constraint, indeks unik parsial, `numeric(18,2)`, `jsonb`, `SELECT ... FOR UPDATE` — SQLite akan meloloskan pelanggaran yang ditolak produksi |
| B4 | `APP_TIMEZONE=UTC`; zona tampilan `Asia/Jakarta` dibaca dari `config('rightclick.display_timezone')` | DB Design 7 — `timestamptz` disimpan UTC, ditampilkan Asia/Jakarta |
| B5 | Peran node dibaca dari `config('rightclick.node.role')` → `App\Domain\Shared\Enums\NodeRole` | Satu basis kode melayani tiga node; pembeda hanya `.env` |
| B6 | `.gitignore` memakai pola `.env.*` dengan pengecualian `.env.example` | ERP Arabica pernah membocorkan kredensial produksi lewat `.env.bak` yang tidak masuk daftar abaikan |
| B7 | `worker` dibatasi 1 proses | H1 — i3-7100 hanya 2 core fisik |

Service `backup` ditambahkan T1.12 — lihat §14 "Backup" untuk detail implementasi.

### Fondasi model (T1.3)

Empat trait/scope di `app/Infrastructure/Persistence/{Concerns,Scopes,Support}`, dipakai seluruh model transaksi mulai T1.4:

| Komponen | Fungsi |
|---|---|
| `Concerns\HasUuidV7` | Primary key UUID v7 (Laravel 12 native `Str::uuid7()`), non-incrementing, key string |
| `Concerns\TracksUserActions` | Mengisi `created_by`/`updated_by` dari `Auth::id()`; tidak menimpa nilai yang sudah diset eksplisit (mis. saat sinkronisasi); `created_by` boleh kosong (seeder `branches` mendahului akun Owner) |
| `Concerns\BelongsToBranch` + `Scopes\BranchScope` | Global scope yang menyaring ke `branch_id` aktif (R12); mengisi `branch_id` otomatis saat `creating`. Lintas cabang yang disengaja (laporan Owner/HQ) memakai `withoutGlobalScope(BranchScope::class)` |
| `Support\BranchContext` | Scoped singleton penampung cabang aktif; sumber nilainya (sesi, `default_branch_id`) baru diwire di T1.4/T2.5 |
| `Support\MigrationMacros` | Macro `Blueprint`: `uuidPrimaryKey()`, `userStamps()` (nullable, FK `users` RESTRICT), `branchId()` (FK `branches` RESTRICT). Granular secara sengaja — tidak semua tabel memakai kombinasi yang sama (`audit_logs`/`stock_mutations` append-only tidak memakai `userStamps()` atau soft delete) |

Soft delete memakai `Illuminate\Database\Eloquent\SoftDeletes` bawaan Laravel langsung pada model — tidak dibungkus trait tambahan, karena tidak ada perilaku RIGHTCLICK-spesifik yang perlu ditambahkan di atasnya.

---

**Lokasi Simpan:** root repositori GitHub RIGHTCLICK, dan salinan di `FOUNDER MODE/04_PROJECTS/ACTIVE/HS-RIGHTCLICK/03_OUTPUT/CLAUDE.md`
