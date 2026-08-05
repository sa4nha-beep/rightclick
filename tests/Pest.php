<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Arch');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Infrastructure\Persistence\Models\Branch;
use App\Infrastructure\Persistence\Models\Permission;
use App\Infrastructure\Persistence\Models\Role;
use App\Infrastructure\Persistence\Models\User;
use Illuminate\Support\Str;

/**
 * Membuat Branch nyata untuk test Policy (T1.6). Kode acak agar tidak
 * bentrok dengan unique constraint antar test — aman karena setiap test
 * dibungkus transaksi DB yang di-rollback di afterEach pemanggilnya.
 */
function makeTestBranch(array $attributes = []): Branch
{
    return Branch::create(array_merge([
        'code' => 'TST-'.Str::upper(Str::random(6)),
        'name' => 'Cabang Uji',
        'is_hq' => false,
        'is_active' => true,
    ], $attributes));
}

/**
 * Membuat User nyata, opsional dengan permission tertentu terpasang lewat
 * role sekali-pakai. Dipakai test Policy (T1.6) untuk membuktikan
 * otorisasi berbasis spatie/laravel-permission, bukan mock.
 */
function makeTestUser(array $permissionNames = []): User
{
    $branch = makeTestBranch();

    $user = User::create([
        'name' => 'Pengguna Uji',
        'username' => 'uji_'.Str::lower(Str::random(12)),
        'email' => Str::lower(Str::random(12)).'@rightclick.test',
        'password' => 'password',
        'default_branch_id' => $branch->id,
        'is_active' => true,
    ]);

    if ($permissionNames !== []) {
        $role = Role::create([
            'name' => 'role_uji_'.Str::lower(Str::random(12)),
            'guard_name' => 'web',
        ]);

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
            $role->givePermissionTo($permission);
        }

        $user->assignRole($role);
    }

    return $user;
}
