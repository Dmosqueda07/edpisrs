<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportAdmin(): User
{
    return User::factory()->create(['role' => Role::Administrator]);
}

it('restricts reports to active administrators and super administrators', function () {
    $this->actingAs(User::factory()->create(['role' => Role::Staff]))
        ->get(route('reports.index'))->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => Role::Administrator]))
        ->get(route('reports.index'))->assertOk();

    $this->actingAs(User::factory()->create(['role' => Role::SuperAdministrator]))
        ->get(route('reports.index'))->assertOk();

    $inactive = User::factory()->create(['role' => Role::Administrator, 'is_active' => false]);
    $this->actingAs($inactive)->get(route('reports.index'))->assertForbidden();
});

it('shows accomplishments completed on both date boundaries and their total', function () {
    $admin = reportAdmin();
    $requester = User::factory()->create();
    $type = RequestType::factory()->create(['name' => 'Device setup']);
    ItSupportRequest::factory()->for($requester, 'requester')->create([
        'request_type_id' => $type->id, 'requested_at' => '2026-06-01 00:00:00', 'completed_at' => '2026-06-30 23:59:59',
        'resolution_notes' => 'Installed the required software', 'status' => ItSupportRequestStatus::Closed,
    ]);
    ItSupportRequest::factory()->for($requester, 'requester')->create([
        'requested_at' => '2026-06-30 23:59:59', 'completed_at' => '2026-07-01 00:00:00',
    ]);

    $this->actingAs($admin)->get(route('reports.index', ['preset' => 'custom', 'start' => '2026-06-01', 'end' => '2026-06-30']))
        ->assertOk()->assertSee('Installed the required software')->assertSee('Total completed: 1');
});

it('shows division counts and percentages from requests received in the period', function () {
    $admin = reportAdmin();
    $user = User::factory()->create();
    foreach ([Division::EDP, Division::EDP, Division::CA] as $division) {
        ItSupportRequest::factory()->for($user, 'requester')->create(['division' => $division->value, 'requested_at' => '2026-06-15 12:00:00']);
    }

    $this->actingAs($admin)->get(route('reports.index', ['preset' => 'custom', 'start' => '2026-06-01', 'end' => '2026-06-30', 'tab' => 'division']))
        ->assertOk()->assertSee('66.7%')->assertSee('33.3%')->assertSee('100%');
});

it('orders request type counts from highest to lowest', function () {
    $admin = reportAdmin();
    $user = User::factory()->create();
    $common = RequestType::factory()->create(['name' => 'Password reset']);
    $rare = RequestType::factory()->create(['name' => 'New equipment']);
    foreach ([$common, $common, $rare] as $type) {
        ItSupportRequest::factory()->for($user, 'requester')->create(['request_type_id' => $type->id, 'requested_at' => '2026-06-15 12:00:00']);
    }

    $this->actingAs($admin)->get(route('reports.index', ['preset' => 'custom', 'start' => '2026-06-01', 'end' => '2026-06-30', 'tab' => 'type']))
        ->assertOk()->assertSeeInOrder(['Password reset', 'New equipment'])->assertSee('3');
});

it('shows plain language turnaround averages per type and technician', function () {
    $admin = reportAdmin();
    $user = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP, 'role' => Role::Staff]);
    $type = RequestType::factory()->create(['name' => 'Network support']);
    ItSupportRequest::factory()->for($user, 'requester')->create([
        'request_type_id' => $type->id, 'assigned_technician_id' => $technician->user_id,
        'requested_at' => '2026-06-10 00:00:00', 'assigned_at' => '2026-06-11 03:00:00', 'completed_at' => '2026-06-12 05:00:00',
    ]);

    $this->actingAs($admin)->get(route('reports.index', ['preset' => 'custom', 'start' => '2026-06-01', 'end' => '2026-06-30', 'tab' => 'turnaround']))
        ->assertOk()->assertSee('1 day 3 hours')->assertSee('1 day 2 hours')->assertSee('Network support')->assertSee($technician->full_name);
});

it('summarizes all request statuses for the received date range', function () {
    $admin = reportAdmin();
    $user = User::factory()->create();
    foreach ([ItSupportRequestStatus::Submitted, ItSupportRequestStatus::Resolved, ItSupportRequestStatus::Closed] as $status) {
        ItSupportRequest::factory()->for($user, 'requester')->create(['status' => $status, 'requested_at' => '2026-06-15 12:00:00']);
    }

    $this->actingAs($admin)->get(route('reports.index', ['preset' => 'custom', 'start' => '2026-06-01', 'end' => '2026-06-30', 'tab' => 'status']))
        ->assertOk()->assertSee('Open')->assertSee('1')->assertSee('Resolved')->assertSee('2');
});

it('validates custom date ranges', function () {
    $this->actingAs(reportAdmin())->get(route('reports.index', ['preset' => 'custom', 'start' => '2026-06-30', 'end' => '2026-06-01']))
        ->assertSessionHasErrors('end');
});
