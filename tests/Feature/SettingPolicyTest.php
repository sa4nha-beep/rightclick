<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\NodeRole;
use App\Infrastructure\Persistence\Models\Setting;
use App\Infrastructure\Persistence\Policies\SettingPolicy;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::beginTransaction();
    config(['rightclick.node.role' => NodeRole::Hq->value]);
});

afterEach(function () {
    DB::rollBack();
});

it('viewAny memerlukan permission manage_settings', function () {
    $policy = new SettingPolicy;

    expect($policy->viewAny(makeTestUser(['manage_settings'])))->toBeTrue()
        ->and($policy->viewAny(makeTestUser()))->toBeFalse();
});

it('view memerlukan permission manage_settings', function () {
    $policy = new SettingPolicy;
    $setting = Setting::create(['key' => 'test.view', 'value' => 1]);

    expect($policy->view(makeTestUser(['manage_settings']), $setting))->toBeTrue()
        ->and($policy->view(makeTestUser(), $setting))->toBeFalse();
});

it('create memerlukan permission manage_settings dan node HQ', function () {
    $policy = new SettingPolicy;

    expect($policy->create(makeTestUser(['manage_settings'])))->toBeTrue()
        ->and($policy->create(makeTestUser()))->toBeFalse();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->create(makeTestUser(['manage_settings'])))->toBeFalse();
});

it('update memerlukan permission manage_settings dan node HQ', function () {
    $policy = new SettingPolicy;
    $setting = Setting::create(['key' => 'test.update', 'value' => 1]);

    expect($policy->update(makeTestUser(['manage_settings']), $setting))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->update(makeTestUser(['manage_settings']), $setting))->toBeFalse();
});

it('delete memerlukan permission manage_settings dan node HQ', function () {
    $policy = new SettingPolicy;
    $setting = Setting::create(['key' => 'test.delete', 'value' => 1]);

    expect($policy->delete(makeTestUser(['manage_settings']), $setting))->toBeTrue();

    config(['rightclick.node.role' => NodeRole::Branch->value]);
    expect($policy->delete(makeTestUser(['manage_settings']), $setting))->toBeFalse();
});
