<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ItSupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows every request and eligible technicians in the administrator intake queue', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create([
        'first_name' => 'EDP',
        'last_name' => 'Technician',
        'division' => Division::EDP,
    ]);
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'details' => 'Printer is not responding.',
    ]);

    $this->actingAs($administrator)
        ->get(route('edp.it-support-requests.index'))
        ->assertOk()
        ->assertSee('#'.$supportRequest->getKey())
        ->assertSee('Printer is not responding.')
        ->assertSee('EDP Technician');
});

it('allows an administrator to assign an active EDP technician and marks the request assigned', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create([
        'division' => Division::EDP,
        'status' => UserStatus::Active,
        'is_active' => true,
    ]);
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.assign', $supportRequest), [
            'technician_id' => $technician->user_id,
        ])
        ->assertRedirect(route('edp.it-support-requests.index'));

    $supportRequest->refresh();

    expect($supportRequest->assigned_technician_id)->toBe($technician->user_id)
        ->and($supportRequest->technician->is($technician))->toBeTrue()
        ->and($supportRequest->assigned_at)->not->toBeNull()
        ->and($supportRequest->status)->toBe(ItSupportRequestStatus::Assigned)
        ->and($supportRequest->logs()->where('action', 'assigned')->value('to_status'))
        ->toBe(ItSupportRequestStatus::Assigned->value);
});

it('logs reassignment from the previous technician', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $firstTechnician = User::factory()->create(['division' => Division::EDP]);
    $secondTechnician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'assigned_technician_id' => $firstTechnician->user_id,
        'status' => ItSupportRequestStatus::Assigned,
    ]);

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.assign', $supportRequest), [
            'technician_id' => $secondTechnician->user_id,
        ])
        ->assertRedirect(route('edp.it-support-requests.index'));

    $log = $supportRequest->logs()->where('action', 'reassigned')->firstOrFail();
    expect($supportRequest->fresh()->assigned_technician_id)->toBe($secondTechnician->user_id)
        ->and($log->user_id)->toBe($administrator->user_id)
        ->and($log->from_status)->toBe(ItSupportRequestStatus::Assigned->value)
        ->and($log->to_status)->toBe(ItSupportRequestStatus::Assigned->value)
        ->and($log->remarks)->toContain((string) $firstTechnician->user_id);
});

it('rejects assignment to users outside the active EDP technician scope', function (array $technicianAttributes) {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create($technicianAttributes);
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.assign', $supportRequest), [
            'technician_id' => $technician->user_id,
        ])
        ->assertSessionHasErrors('technician_id');

    expect($supportRequest->fresh()->assigned_technician_id)->toBeNull()
        ->and($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Submitted);
})->with([
    'wrong division' => [['division' => Division::CA]],
    'suspended' => [['division' => Division::EDP, 'status' => UserStatus::Suspended]],
    'disabled' => [['division' => Division::EDP, 'is_active' => false]],
]);

it('allows the assigned technician to view the request', function () {
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create(['assigned_technician_id' => $technician->user_id]);

    $this->actingAs($technician)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk();
});

it('denies employees access to the EDP assignment queue', function () {
    $employee = User::factory()->create(['role' => Role::Staff]);

    $this->actingAs($employee)
        ->get(route('edp.it-support-requests.index'))
        ->assertForbidden();
});

it('denies non-administrators permission to assign requests', function () {
    $employee = User::factory()->create(['role' => Role::Staff]);
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($employee)
        ->patch(route('edp.it-support-requests.assign', $supportRequest), [
            'technician_id' => $technician->user_id,
        ])
        ->assertForbidden();
});
