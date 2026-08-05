<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\User;
use App\Infrastructure\Persistence\Models\UserBranch;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder phase 1 (urutan dari DB Design §9.2):
     *   1. branches (Cabang A, Cabang B, HQ)
     *   3. users (akun Owner awal)
     *   user_branches (asosiasi Owner ke semua tiga cabang)
     *
     * Fase 2 (roles + permissions) adalah T1.5 — akan memanggil
     * `$this->call(PermissionSeeder::class)`.
     */
    public function run(): void
    {
        $branchA = Branch::firstOrCreate(
            ['code' => 'HK-A'],
            [
                'name' => 'Cabang Kudus A',
                'address' => 'Jln. Muria, Kudus',
                'pic_name' => 'PIC Cabang A',
                'is_hq' => false,
                'is_active' => true,
            ]
        );

        $branchB = Branch::firstOrCreate(
            ['code' => 'HK-B'],
            [
                'name' => 'Cabang Kudus B',
                'address' => 'Jln. Pendung, Kudus',
                'pic_name' => 'PIC Cabang B',
                'is_hq' => false,
                'is_active' => true,
            ]
        );

        $branchHQ = Branch::firstOrCreate(
            ['code' => 'HK-HQ'],
            [
                'name' => 'Markas HQ',
                'address' => 'Kudus, Jawa Tengah',
                'pic_name' => 'Owner',
                'is_hq' => true,
                'is_active' => true,
            ]
        );

        $owner = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Owner HAEN KOMPUTER',
                'email' => 'admin@rightclick.local',
                'password' => 'password',
                'default_branch_id' => $branchHQ->id,
                'is_active' => true,
            ]
        );

        // Asosiasi Owner ke semua tiga cabang
        UserBranch::firstOrCreate(['user_id' => $owner->id, 'branch_id' => $branchA->id]);
        UserBranch::firstOrCreate(['user_id' => $owner->id, 'branch_id' => $branchB->id]);
        UserBranch::firstOrCreate(['user_id' => $owner->id, 'branch_id' => $branchHQ->id]);

        // Phase 2 — roles dan permissions
        $this->call(PermissionSeeder::class);

        // Assign owner role ke Owner account
        if (! $owner->hasRole('owner')) {
            $owner->assignRole('owner');
        }
    }
}
