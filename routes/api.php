<?php

declare(strict_types=1);

use App\Http\Controllers\Sync\SyncAckController;
use App\Http\Controllers\Sync\SyncEventsController;
use App\Http\Controllers\Sync\SyncHealthController;
use App\Http\Controllers\Sync\SyncMasterCheckController;
use App\Http\Controllers\Sync\SyncMasterSnapshotController;
use App\Http\Controllers\Sync\SyncPartnerUpsertController;
use App\Http\Middleware\AuthenticateSyncNode;
use App\Http\Middleware\EnsureNodeIsHq;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Protokol Sinkronisasi (T5.8, CLAUDE.md §8)
|--------------------------------------------------------------------------
|
| SATU-SATUNYA API di RIGHTCLICK — "Tidak ada API CRUD untuk pengguna
| akhir" (§8). Hanya diakses antar NODE lewat VPN dengan token per node
| (`AuthenticateSyncNode`), dan hanya bermakna di node HQ
| (`EnsureNodeIsHq`) — node cabang memakai basis kode yang sama tapi
| tidak pernah menerima panggilan masuk ke rute ini.
|
*/
Route::prefix('v1/sync')
    ->middleware([EnsureNodeIsHq::class, AuthenticateSyncNode::class])
    ->group(function (): void {
        Route::post('events', SyncEventsController::class);
        Route::post('ack', SyncAckController::class);
        Route::get('health', SyncHealthController::class);
        Route::post('master-check', SyncMasterCheckController::class);
        Route::get('master-snapshot/{table}', SyncMasterSnapshotController::class);
        Route::post('partner-upsert', SyncPartnerUpsertController::class);
    });
