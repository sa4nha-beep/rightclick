<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder cabang (Cabang A, Cabang B, HQ) dan akun Owner awal adalah
     * lingkup T1.4. R9 menetapkan clean start — tanpa data historis, tanpa
     * akun contoh yang tidak disengaja ikut ke produksi.
     */
    public function run(): void
    {
        //
    }
}
