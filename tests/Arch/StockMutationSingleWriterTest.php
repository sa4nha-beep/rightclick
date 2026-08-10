<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * R1 — "stock_mutations adalah satu-satunya sumber kebenaran stok ...
 * Hanya StockLedgerService yang boleh menulis" (T3.2, simpul kritis).
 *
 * Pemindaian berkas literal atas seluruh `app/`, bukan API arch Pest
 * bawaan — deterministik dan tidak bergantung pada dukungan Pest untuk
 * kasus "kelas hanya boleh dipakai dari satu berkas tertentu", yang tidak
 * dipastikan tersedia.
 */
it('hanya StockLedgerService yang menulis StockMutation (R1)', function () {
    $allowedFile = app_path('Application/Services/StockLedgerService.php');

    $writePatterns = [
        '/StockMutation::create\s*\(/',
        '/StockMutation::insert\s*\(/',
        '/StockMutation::forceCreate\s*\(/',
        '/StockMutation::query\(\)\s*->\s*insert\s*\(/',
        '/new\s+StockMutation\s*\(/',
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
        'Penulisan StockMutation ditemukan di luar StockLedgerService (R1): '.implode(', ', $violations),
    );
});
