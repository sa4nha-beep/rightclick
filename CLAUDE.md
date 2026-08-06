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

**Kemajuan:** T1.1–T1.9 selesai (T1.4 identity tables/models/seeder, T1.5 authorization framework spatie, T1.6 model policies Branch/User/UserBranch, T1.7 `DocumentNumberService` + `document_sequences`, T1.8 trait `HasDocumentState` + `DocumentStateService` draft→final→void, T1.9 tabel `settings` + seeder ambang TH1–TH5c + `ApprovalService`/tabel `approvals`). Task berikutnya: **T1.10** (tema Filament: token warna, Inter lokal, sidebar, dark mode nonaktif).

> **Catatan T1.9 (diselesaikan):** permission baru T1.9 memakai gaya `snake_case` mengikuti `PermissionSeeder` yang sudah ada, bukan notasi titik `HS-PERM-RIGHTCLICK-v1.1`. Ketidaksesuaian ini direkonsiliasi dengan menerbitkan `HS-PERM-RIGHTCLICK-v1.2` (revisi penamaan mengikuti kode, status Draft menunggu approval COO) — lihat §17.

### Tiga simpul yang tidak boleh dilewati

| Simpul | Alasan |
|---|---|
| **T3.2** — `StockLedgerService` sebagai penulis tunggal | Bila modul lain menulis mutasi langsung, FIFO dan penguncian terlewat, stok rusak tanpa jejak |
| **T5.7** — outbox dalam transaksi yang sama | Bila di luar transaksi, ada dokumen final yang tidak pernah sampai HQ dan tidak ada yang tahu |
| **T5.2** — `unit_cost` termasuk PPN | Bila diambil nilai DPP, seluruh HPP terlalu rendah dan margin dilaporkan lebih besar dari kenyataan |

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
| **PT1–PT16** | Otorisasi negatif — memastikan yang tidak boleh benar-benar ditolak | `HS-PERM-RIGHTCLICK-v1.1` |
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

Service `backup` belum ada di `docker-compose.yml`; itu lingkup **T1.12**, dan T1.12 mengunci penyelesaian Fase 1.

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
