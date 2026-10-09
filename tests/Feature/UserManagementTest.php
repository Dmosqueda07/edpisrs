<?php

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\UserManagementAuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('logging.channels.audit', ['driver' => 'null']);
});

it('limits user management to active administrators', function () {
    $staff = User::factory()->create();
    $suspendedAdministrator = User::factory()->create([
        'role' => Role::Administrator,
        'status' => UserStatus::Suspended,
    ]);

    $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
    $this->actingAs($suspendedAdministrator)->get(route('users.index'))->assertForbidden();

    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $this->actingAs($administrator)->get(route('users.index'))->assertOk();
});

it('filters users by name, email, status, division, and role', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $match = User::factory()->create([
        'first_name' => 'Morgan',
        'last_name' => 'Lane',
        'email' => 'morgan.lane@example.com',
        'division' => Division::EDP,
        'role' => Role::Receiving,
        'status' => UserStatus::Pending,
    ]);
    User::factory()->create(['first_name' => 'Another', 'email' => 'another@example.com']);

    $this->actingAs($administrator)
        ->get(route('users.index', [
            'search' => 'morgan.lane@example.com',
            'status' => 'pending',
            'division' => Division::EDP->value,
            'role' => Role::Receiving->value,
        ]))
        ->assertOk()
        ->assertSee($match->email)
        ->assertDontSee('another@example.com');
});

it('approves pending users and records the old and new state to audit', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $pending = User::factory()->create(['status' => UserStatus::Pending]);
    $audit = $this->mock(UserManagementAuditLogger::class);
    $audit->shouldReceive('record')
        ->once()
        ->with(
            Mockery::on(fn (User $actor): bool => $actor->is($administrator)),
            Mockery::on(fn (User $target): bool => $target->is($pending)),
            'approve',
            ['status' => 'pending', 'is_active' => true],
            ['status' => 'active', 'is_active' => true],
        );

    $this->actingAs($administrator)
        ->patch(route('users.approve', $pending))
        ->assertRedirect();

    expect($pending->refresh()->status)->toBe(UserStatus::Active);
});

it('lets administrators manage standard accounts and records division, role, and password actions', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $staff = User::factory()->create();

    $this->actingAs($administrator)
        ->patch(route('users.role', $staff), ['role' => Role::Receiving->value])
        ->assertRedirect();
    expect($staff->refresh()->role)->toBe(Role::Receiving);

    $this->patch(route('users.division', $staff), ['division' => Division::EDP->value])
        ->assertRedirect();
    expect($staff->refresh()->division)->toBe(Division::EDP);

    $this->put(route('users.password', $staff), [
        'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password',
    ])->assertRedirect();
    expect(Hash::check('temporary-password', $staff->refresh()->password))->toBeTrue();
});

it('allows an administrator to suspend and reactivate a standard user', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $staff = User::factory()->create();

    $this->actingAs($administrator)->patch(route('users.suspend', $staff))->assertRedirect();
    expect($staff->refresh()->status)->toBe(UserStatus::Suspended);

    $this->patch(route('users.reactivate', $staff))->assertRedirect();
    expect($staff->refresh()->status)->toBe(UserStatus::Active);
});

it('prevents administrators from changing or suspending administrator accounts', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $targetAdministrator = User::factory()->create(['role' => Role::Administrator]);

    $this->actingAs($administrator)
        ->patch(route('users.role', $targetAdministrator), ['role' => Role::Staff->value])
        ->assertForbidden();

    $this->patch(route('users.suspend', $targetAdministrator))->assertForbidden();
});

it('prevents administrators from granting administrator roles', function (Role $role) {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $staff = User::factory()->create();

    $this->actingAs($administrator)
        ->patch(route('users.role', $staff), ['role' => $role->value])
        ->assertForbidden();
})->with([
    'administrator' => [Role::Administrator],
    'super administrator' => [Role::SuperAdministrator],
]);

it('prevents users from changing their own role or suspending themselves', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);

    $this->actingAs($administrator)
        ->patch(route('users.role', $administrator), ['role' => Role::Staff->value])
        ->assertForbidden();

    $this->patch(route('users.suspend', $administrator))->assertForbidden();
});

it('prevents demoting or suspending the last active super administrator', function () {
    $superAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $staff = User::factory()->create();

    $this->actingAs($superAdministrator)
        ->patch(route('users.role', $superAdministrator), ['role' => Role::Staff->value])
        ->assertForbidden();

    $this->patch(route('users.suspend', $superAdministrator))->assertForbidden();
    expect($superAdministrator->refresh()->role)->toBe(Role::SuperAdministrator)
        ->and($superAdministrator->status)->toBe(UserStatus::Active);
});

it('protects the last active super administrator against another privileged actor', function () {
    $lastSuperAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $actor = User::factory()->make(['role' => Role::SuperAdministrator]);

    expect(Gate::forUser($actor)->allows('suspend', $lastSuperAdministrator))->toBeFalse()
        ->and(Gate::forUser($actor)->allows('changeRole', [
            $lastSuperAdministrator,
            Role::Staff->value,
        ]))->toBeFalse();
});

it('allows a super administrator to grant or change privileged roles and suspend another super administrator', function () {
    $superAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $staff = User::factory()->create();
    $otherSuperAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);

    $this->actingAs($superAdministrator)
        ->patch(route('users.role', $staff), ['role' => Role::Administrator->value])
        ->assertRedirect();
    expect($staff->refresh()->role)->toBe(Role::Administrator);

    $this->patch(route('users.role', $otherSuperAdministrator), ['role' => Role::Staff->value])
        ->assertRedirect();
    expect($otherSuperAdministrator->refresh()->role)->toBe(Role::Staff);

    $anotherSuperAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $this->patch(route('users.suspend', $anotherSuperAdministrator))->assertRedirect();
    expect($anotherSuperAdministrator->refresh()->status)->toBe(UserStatus::Suspended);
});

it('only lets a super administrator reactivate or reset privileged accounts', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $suspendedSuperAdministrator = User::factory()->create([
        'role' => Role::SuperAdministrator,
        'status' => UserStatus::Suspended,
    ]);

    $this->actingAs($administrator)
        ->patch(route('users.reactivate', $suspendedSuperAdministrator))
        ->assertForbidden();

    $this->put(route('users.password', $suspendedSuperAdministrator), [
        'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password',
    ])->assertForbidden();
});

it('records admin password resets without writing the password value to the audit data', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $target = User::factory()->create();
    $audit = $this->mock(UserManagementAuditLogger::class);
    $audit->shouldReceive('record')
        ->once()
        ->with(
            Mockery::on(fn (User $actor): bool => $actor->is($administrator)),
            Mockery::on(fn (User $actualTarget): bool => $actualTarget->is($target)),
            'reset_password',
            ['password' => '[redacted]'],
            ['password' => '[temporary password set; value redacted]'],
        );

    $this->actingAs($administrator)->put(route('users.password', $target), [
        'password' => 'temporary-password',
        'password_confirmation' => 'temporary-password',
    ])->assertRedirect();
});

it('reactivates an inactive account', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $inactive = User::factory()->create(['is_active' => false]);

    $this->actingAs($administrator)->patch(route('users.reactivate', $inactive))->assertRedirect();
    expect($inactive->refresh()->is_active)->toBeTrue();
});

it('does not reactivate a pending account in place of the approval action', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $pending = User::factory()->create(['status' => UserStatus::Pending]);

    $this->actingAs($administrator)
        ->patch(route('users.reactivate', $pending))
        ->assertForbidden();

    expect($pending->refresh()->status)->toBe(UserStatus::Pending);
});

it('writes user audit entries to the dedicated audit channel with actor, target, action, and values', function () {
    $actor = User::factory()->create(['role' => Role::Administrator]);
    $target = User::factory()->create();
    $logger = Mockery::mock(LoggerInterface::class);

    Log::shouldReceive('channel')->once()->with('audit')->andReturn($logger);
    $logger->shouldReceive('info')->once()->with('User management action', [
        'actor_user_id' => $actor->getKey(),
        'target_user_id' => $target->getKey(),
        'action' => 'change_division',
        'old_values' => ['division' => Division::CA->value],
        'new_values' => ['division' => Division::EDP->value],
    ]);

    (new UserManagementAuditLogger)->record(
        $actor,
        $target,
        'change_division',
        ['division' => Division::CA->value],
        ['division' => Division::EDP->value],
    );
});
