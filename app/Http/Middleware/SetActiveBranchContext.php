<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Support\BranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengisi `BranchContext` dari permintaan HTTP yang terautentikasi (T2.8).
 *
 * Tiga sumber nilai yang diantisipasi sejak `BranchContext` dibuat
 * (lihat docblock-nya): sesi pengguna, `default_branch_id` pada `users`,
 * atau pemilihan cabang aktif di antarmuka. Diselesaikan di sini sebagai:
 *
 *   1. `session('active_branch_id')` bila ada DAN tervalidasi sebagai
 *      cabang yang ditugaskan ke pengguna (`user_branches`) — sumber ini
 *      belum punya UI penulis (belum ada branch-switcher di Filament,
 *      T2.9+), tapi kontrak baca-nya sudah siap dipakai begitu UI itu ada.
 *   2. `default_branch_id` pengguna — fallback, juga jalur utama untuk
 *      pengguna bercabang tunggal (mayoritas Kasir/Gudang/Admin).
 *
 * Validasi terhadap `user_branches` WAJIB pada sumber (1) — tanpa itu,
 * nilai sesi yang dimanipulasi (atau bug UI branch-switcher di masa
 * depan) bisa membuat pengguna menulis/membaca data seolah dari cabang
 * yang tidak ditugaskan kepadanya, lolos dari `BranchScope` tanpa
 * terdeteksi (BranchScope hanya menyaring ke cabang AKTIF, bukan
 * memvalidasi apakah pengguna berhak berada di cabang itu).
 *
 * Didaftarkan di `AdminPanelProvider::authMiddleware()` setelah
 * `Authenticate::class` — harus berjalan setelah identitas pengguna
 * dipastikan, sebelum resource/Livewire component apa pun membaca
 * `BranchContext`.
 */
class SetActiveBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user !== null) {
            app(BranchContext::class)->set($this->resolveBranchId($user, $request));
        }

        return $next($request);
    }

    private function resolveBranchId(User $user, Request $request): string
    {
        $sessionBranchId = $request->hasSession()
            ? $request->session()->get('active_branch_id')
            : null;

        if (is_string($sessionBranchId) && $user->branches()->where('branch_id', $sessionBranchId)->exists()) {
            return $sessionBranchId;
        }

        return $user->default_branch_id;
    }
}
