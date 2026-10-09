<?php

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\NewEmployeeRegistered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('renders the public employee registration form', function () {
    $this->get('/register')->assertOk()->assertSee('Register for an employee account');
});

it('creates a pending staff account and ignores submitted role and status', function () {
    Notification::fake();
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $superAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $inactiveAdministrator = User::factory()->create([
        'role' => Role::Administrator,
        'is_active' => false,
    ]);

    $response = $this->post(route('register.store'), [
        'first_name' => 'Jordan',
        'last_name' => 'Employee',
        'email' => 'jordan.employee@example.com',
        'division' => Division::EDP->value,
        'password' => 'employee-password',
        'password_confirmation' => 'employee-password',
        'role' => Role::SuperAdministrator->value,
        'status' => UserStatus::Active->value,
    ]);

    $response->assertRedirect(route('login'));
    $user = User::query()->where('email', 'jordan.employee@example.com')->firstOrFail();
    expect($user->role)->toBe(Role::Staff)
        ->and($user->status)->toBe(UserStatus::Pending)
        ->and($user->division)->toBe(Division::EDP);

    Notification::assertSentTo($administrator, NewEmployeeRegistered::class);
    Notification::assertSentTo($superAdministrator, NewEmployeeRegistered::class);
    Notification::assertNotSentTo($inactiveAdministrator, NewEmployeeRegistered::class);
    expect(new NewEmployeeRegistered($user))->toBeInstanceOf(ShouldQueue::class)
        ->and(class_uses_recursive(NewEmployeeRegistered::class))->toContain(SerializesModels::class);
});

it('validates employee registration email, division, and password confirmation', function () {
    $this->from('/register')->post(route('register.store'), [
        'first_name' => 'Jordan',
        'last_name' => 'Employee',
        'email' => str_repeat('a', 214).'@example.com',
        'division' => 'not-a-division',
        'password' => 'employee-password',
        'password_confirmation' => 'not-the-same',
    ])->assertRedirect('/register')
        ->assertSessionHasErrors(['email', 'division', 'password']);

    User::factory()->create(['email' => 'taken@example.com']);
    $this->post(route('register.store'), [
        'first_name' => 'Jordan',
        'last_name' => 'Employee',
        'email' => 'taken@example.com',
        'division' => Division::EDP->value,
        'password' => 'employee-password',
        'password_confirmation' => 'employee-password',
    ])->assertSessionHasErrors(['email']);
});

it('restricts registration to configured email domains when configured', function () {
    config()->set('registration.allowed_domains', ['qc.gov.ph']);

    $this->from('/register')->post(route('register.store'), [
        'first_name' => 'Jordan',
        'last_name' => 'Employee',
        'email' => 'jordan@example.com',
        'division' => Division::EDP->value,
        'password' => 'employee-password',
        'password_confirmation' => 'employee-password',
    ])->assertRedirect('/register')->assertSessionHasErrors(['email']);

    $this->post(route('register.store'), [
        'first_name' => 'Jordan',
        'last_name' => 'Employee',
        'email' => 'jordan@qc.gov.ph',
        'division' => Division::EDP->value,
        'password' => 'employee-password',
        'password_confirmation' => 'employee-password',
    ])->assertRedirect(route('login'));
});

it('rate limits registration submissions', function () {
    expect(Route::getRoutes()->getByName('register.store')->gatherMiddleware())
        ->toContain('throttle:5,1');
});
