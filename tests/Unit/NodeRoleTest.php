<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;

it('membedakan node HQ dan node cabang', function () {
    expect(NodeRole::Hq->isHq())->toBeTrue()
        ->and(NodeRole::Hq->isBranch())->toBeFalse()
        ->and(NodeRole::Branch->isBranch())->toBeTrue()
        ->and(NodeRole::Branch->isHq())->toBeFalse();
});

it('hanya mengizinkan HQ menulis master data', function () {
    // HS-ARCH-RIGHTCLICK-v1.1 bagian 1.3 — master data mengalir HQ → cabang,
    // read-only di cabang. Penegakannya di lapisan aplikasi adalah T2.5.
    expect(NodeRole::Hq->canWriteMasterData())->toBeTrue()
        ->and(NodeRole::Branch->canWriteMasterData())->toBeFalse();
});
