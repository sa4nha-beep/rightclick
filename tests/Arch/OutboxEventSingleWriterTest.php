<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * T5.7, simpul kritis — satu-satunya jalur tulis `outbox_events` adalah
 * `OutboxService` (pola persis `StockMutationSingleWriterTest`/R1,
 * `CashEntrySingleWriterTest`/AC-21).
 */
it('hanya OutboxService yang menulis OutboxEvent', function () {
    $allowedFile = app_path('Application/Services/OutboxService.php');

    $writePatterns = [
        '/OutboxEvent::create\s*\(/',
        '/OutboxEvent::insert\s*\(/',
        '/OutboxEvent::forceCreate\s*\(/',
        '/OutboxEvent::query\(\)\s*->\s*insert\s*\(/',
        '/new\s+OutboxEvent\s*\(/',
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
        'Penulisan OutboxEvent ditemukan di luar OutboxService: '.implode(', ', $violations),
    );
});
