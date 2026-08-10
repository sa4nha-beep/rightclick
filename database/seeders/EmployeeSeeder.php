<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\Employee;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Seeder Fase 2 (T2.6): contoh karyawan untuk pengembangan dan demo —
     * bukan data produksi (R9, clean start).
     */
    public function run(): void
    {
        $owner = User::query()->where('username', 'admin')->first();

        Employee::firstOrCreate(
            ['id_number' => '3319012345670001'],
            [
                'user_id' => $owner?->id,
                'name' => $owner->name ?? 'Owner HAEN KOMPUTER',
                'date_of_birth' => '1985-03-12',
                'position' => 'Owner',
                'department' => 'Manajemen',
                'phone' => '0812-3456-7890',
                'hired_at' => '2020-01-01',
                'is_active' => true,
            ]
        );

        Employee::firstOrCreate(
            ['id_number' => '3319012345670002'],
            [
                'name' => 'Dewi Anggraini',
                'date_of_birth' => '1998-07-20',
                'position' => 'Kasir',
                'department' => 'Penjualan',
                'phone' => '0813-2233-4455',
                'hired_at' => '2023-05-15',
                'is_active' => true,
            ]
        );

        Employee::firstOrCreate(
            ['id_number' => '3319012345670003'],
            [
                'name' => 'Rizky Pratama',
                'date_of_birth' => '1996-11-02',
                'position' => 'Staf Gudang',
                'department' => 'Gudang',
                'phone' => '0813-9988-7766',
                'hired_at' => '2022-09-01',
                'is_active' => true,
            ]
        );

        Employee::firstOrCreate(
            ['id_number' => '3319012345670004'],
            [
                'name' => 'Nur Hidayat',
                'date_of_birth' => '1990-02-14',
                'position' => 'Admin',
                'department' => 'Operasional',
                'phone' => '0812-1122-3344',
                'hired_at' => '2021-03-10',
                'is_active' => true,
            ]
        );

        Employee::firstOrCreate(
            ['id_number' => '3319012345670005'],
            [
                'name' => 'Fajar Nugroho',
                'date_of_birth' => '1994-06-30',
                'position' => 'Staf Gudang',
                'department' => 'Gudang',
                'phone' => '0813-5566-7788',
                'hired_at' => '2021-08-20',
                'is_active' => false,
                'notes' => 'Resign 2025-12-01',
            ]
        );
    }
}
