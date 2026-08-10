<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * T5.8 — satu-satunya jalur tulis `processed_events` adalah
 * `SyncEventProcessor` (pola persis `StockMutationSingleWriterTest`/R1,
 * `CashEntrySingleWriterTest`/AC-21, `OutboxEventSingleWriterTest`/T5.7).
 */
it('hanya SyncEventProcessor yang menulis ProcessedEvent', function () {
    $allowedFile = app_path('Application/Services/Sync/SyncEventProcessor.php');

    $writePatterns = [
        '/ProcessedEvent::create\s*\(/',
        '/ProcessedEvent::insert\s*\(/',
        '/ProcessedEvent::forceCreate\s*\(/',
        '/ProcessedEvent::query\(\)\s*->\s*insert\s*\(/',
        '/new\s+ProcessedEvent\s*\(/',
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
        'Penulisan ProcessedEvent ditemukan di luar SyncEventProcessor: '.implode(', ', $violations),
    );
});
