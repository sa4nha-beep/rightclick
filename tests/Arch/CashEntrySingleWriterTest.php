<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * AC-21 — "Kas keluar tanpa referensi dokumen → penyimpanan ditolak", dan
 * secara lebih luas: satu-satunya jalur tulis `cash_entries` adalah
 * `CashLedgerService` (T5.4, pola persis `StockMutationSingleWriterTest`
 * untuk R1/T3.2).
 *
 * Pemindaian berkas literal atas seluruh `app/`, bukan API arch Pest
 * bawaan — sama alasan dengan test kakaknya.
 */
it('hanya CashLedgerService yang menulis CashEntry', function () {
    $allowedFile = app_path('Application/Services/CashLedgerService.php');

    $writePatterns = [
        '/CashEntry::create\s*\(/',
        '/CashEntry::insert\s*\(/',
        '/CashEntry::forceCreate\s*\(/',
        '/CashEntry::query\(\)\s*->\s*insert\s*\(/',
        '/new\s+CashEntry\s*\(/',
    ];

    $violations = [];

    $finder = (new Finder)->files()->in(app_path())->name('*.php');

    foreach ($finder as $file) {
        $path = $file->getRealPath();

        if ($path === $allowedFile) {
            continue;
        }

        $contents = file_get_contents($path);

        foreach ($writePatterns as $pattern) {
            if ($contents !== false && preg_match($pattern, $contents) === 1) {
                $violations[] = $path;
            }
        }
    }

    expect($violations)->toBe(
        [],
        'Penulisan CashEntry ditemukan di luar CashLedgerService: '.implode(', ', $violations),
    );
});
