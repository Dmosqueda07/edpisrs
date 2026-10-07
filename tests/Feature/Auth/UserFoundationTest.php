<?php

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('defines the configured divisions and roles', function () {
    expect(array_column(Division::cases(), 'value'))->toBe([
        'ADMIN',
        'PVSD',
        'EDP',
        'TMD',
        'ARMD',
        'PAD',
        'CA',
        'ACA_Admin',
        'ACA_OP',
    ])->and(array_column(Role::cases(), 'value'))->toBe([
        'Super Administrator',
        'Administrator',
        'Receiving',
        'Records Chief',
        'City Assessor',
        'ACA Admin',
        'Division Secretary',
        'Division Head',
        'Staff',
        'ACA OP',
    ]);
});

it('stores user identity, enum values, and a hashed reset code', function () {
    $user = User::factory()->create([
        'first_name' => 'Taylor',
        'last_name' => 'Employee',
        'role' => Role::DivisionHead,
        'division' => Division::EDP,
        'reset_code' => 'reset-secret',
        'reset_expires_at' => now()->addMinutes(15),
    ]);

    expect($user->getKeyName())->toBe('user_id')
        ->and($user->role)->toBe(Role::DivisionHead)
        ->and($user->division)->toBe(Division::EDP)
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->full_name)->toBe('Taylor Employee')
        ->and(Hash::check('reset-secret', $user->getRawOriginal('reset_code')))->toBeTrue();
});

it('scopes technicians to active EDP users only', function () {
    $technician = User::factory()->create(['division' => Division::EDP]);
    $otherDivision = User::factory()->create(['division' => Division::CA]);
    $suspended = User::factory()->create([
        'division' => Division::EDP,
        'status' => UserStatus::Suspended,
    ]);
    $disabled = User::factory()->create([
        'division' => Division::EDP,
        'is_active' => false,
    ]);

    $technicianIds = User::technicians()->pluck('user_id');

    expect($technicianIds)->toContain($technician->user_id)
        ->not->toContain($otherDivision->user_id)
        ->not->toContain($suspended->user_id)
        ->not->toContain($disabled->user_id);
});

it('rejects login for accounts that are not active and enabled', function (array $attributes) {
    $user = User::factory()->create($attributes);

    Volt::test('pages.auth.login')
        ->set('form.email', $user->email)
        ->set('form.password', 'password')
        ->call('login')
        ->assertHasErrors();

    $this->assertGuest();
})->with([
    'pending account' => [['status' => UserStatus::Pending]],
    'suspended account' => [['status' => UserStatus::Suspended]],
    'disabled account' => [['is_active' => false]],
]);
