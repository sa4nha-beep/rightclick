<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});

it('T4.5 — tautan Buka POS tampil di dashboard bagi pengguna dengan create_sale', function () {
    $this->actingAs(makeTestUser(['create_sale']));

    $this->get('/admin')->assertOk()->assertSee('Buka POS');
});

it('T4.5 — tautan Buka POS TIDAK tampil bagi pengguna tanpa create_sale', function () {
    $this->actingAs(makeTestUser(['view_products']));

    $this->get('/admin')->assertOk()->assertDontSee('Buka POS');
});
