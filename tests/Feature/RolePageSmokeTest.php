<?php

use App\Enums\Division;
use App\Enums\Role;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('smoke loads the common and role-restricted pages for every role', function () {
    foreach (Role::cases() as $role) {
        $user = User::factory()->create([
            'role' => $role,
            'division' => Division::CA,
        ]);
        $supportRequest = SupportRequest::factory()->for($user, 'requester')->create();
        $isAdministrator = in_array($role, [Role::Administrator, Role::SuperAdministrator], true);

        $this->actingAs($user)
            ->get(route('dashboard'))->assertOk();
        $this->get(route('profile'))->assertOk();
        $this->get(route('it-support-requests.create'))->assertOk();
        $this->get(route('it-support-requests.index'))->assertOk();
        $this->get(route('it-support-requests.show', $supportRequest))->assertOk();

        $this->get(route('edp.it-support-requests.index'))
            ->assertStatus($isAdministrator ? 200 : 403);
        $this->get(route('edp.assigned-requests.index'))
            ->assertStatus($isAdministrator ? 200 : 403);
        $this->get(route('reports.index'))
            ->assertStatus($isAdministrator ? 200 : 403);
        $this->get(route('activity-logs.index'))
            ->assertStatus($isAdministrator ? 200 : 403);
        $this->get(route('users.index'))
            ->assertStatus($isAdministrator ? 200 : 403);
        $this->get(route('activity-logs.index', ['tab' => 'accounts']))
            ->assertStatus($role === Role::SuperAdministrator ? 200 : 403);
    }
});

it('smoke loads intake, assigned work, and unclaimed request details for an active EDP technician', function () {
    $technician = User::factory()->create([
        'role' => Role::Staff,
        'division' => Division::EDP,
    ]);
    $requester = User::factory()->create();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($technician)
        ->get(route('edp.it-support-requests.index'))->assertOk()
        ->assertSee($supportRequest->ticket_no)
        ->assertSee('Claim');
    $this->get(route('edp.assigned-requests.index'))->assertOk();
    $this->get(route('it-support-requests.show', $supportRequest))->assertOk()->assertSee('Claim');
});
