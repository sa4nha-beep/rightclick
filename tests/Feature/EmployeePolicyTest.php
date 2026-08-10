<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Employee;
use App\Infrastructure\Persistence\Policies\EmployeePolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

function makeTestEmployee(array $attributes = []): Employee
{
    return Employee::factory()->create($attributes);
}

it('viewAny memerlukan permission view_users', function () {
    $policy = new EmployeePolicy;

    expect($policy->viewAny(makeTestUser(['view_users'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission view_users', function () {
    $policy = new EmployeePolicy;
    $employee = makeTestEmployee();

    expect($policy->view(makeTestUser(['view_users']), $employee))->toBeTrue()
        ->and($policy->view(makeTestUser(), $employee))->toBeFalse();
});

it('create ditolak tanpa permission create_users', function () {
    $policy = new EmployeePolicy;

    expect($policy->create(makeTestUser()))->toBeFalse();
});

it('create mengizinkan permission create_users di node HQ', function () {
    $policy = new EmployeePolicy;

    expect($policy->create(makeTestUser(['create_users'])))->toBeTrue();
});

it('create menolak node cabang meski permission tersedia — M02', function () {
    config(['rightclick.node.role' => NodeRole::Branch->value]);
    $policy = new EmployeePolicy;

    expect($policy->create(makeTestUser(['create_users'])))->toBeFalse();
});

it('update memerlukan permission edit_users dan node HQ', function () {
    $policy = new EmployeePolicy;
    $employee = makeTestEmployee();
    $authorized = makeTestUser(['edit_users']);

    expect($policy->update($authorized, $employee))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update($authorized, $employee))->toBeFalse();
});

it('delete memerlukan permission delete_users dan node HQ', function () {
    $policy = new EmployeePolicy;
    $employee = makeTestEmployee();
    $authorized = makeTestUser(['delete_users']);

    expect($policy->delete($authorized, $employee))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete($authorized, $employee))->toBeFalse();
});

it('restore memerlukan permission delete_users dan node HQ', function () {
    $policy = new EmployeePolicy;
    $employee = makeTestEmployee();

    expect((new EmployeePolicy)->restore(makeTestUser(['delete_users']), $employee))->toBeTrue();
});

it('forceDelete selalu ditolak — R5 hanya mengizinkan soft delete', function () {
    $policy = new EmployeePolicy;
    $employee = makeTestEmployee();
    $owner = makeTestUser(['delete_users']);

    expect($policy->forceDelete($owner, $employee))->toBeFalse();
});

it('id_number boleh kosong untuk karyawan tanpa dokumen identitas lengkap', function () {
    $employee = makeTestEmployee(['id_number' => null]);

    expect($employee->id_number)->toBeNull();
});

it('id_number yang terisi tidak boleh duplikat', function () {
    makeTestEmployee(['id_number' => '3319000000000001']);

    expect(fn () => makeTestEmployee(['id_number' => '3319000000000001']))
        ->toThrow(QueryException::class);
});

it('user_id nullable — karyawan tidak wajib punya akun login', function () {
    $employee = makeTestEmployee(['user_id' => null]);

    expect($employee->user_id)->toBeNull()
        ->and($employee->user)->toBeNull();
});

it('karyawan dapat ditautkan ke akun User lewat user_id', function () {
    $user = makeTestUser();
    $employee = makeTestEmployee(['user_id' => $user->id]);

    expect($employee->user->id)->toBe($user->id);
});
