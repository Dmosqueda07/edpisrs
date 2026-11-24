<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\RequestLog;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\RequestClaimed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function requestTechnician(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'division' => Division::EDP,
        'status' => UserStatus::Active,
        'is_active' => true,
        'role' => Role::Staff,
    ], $attributes));
}

it('gives active EDP technicians view and claim access to intake without admin assignment controls', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create();
    $technician = requestTechnician();

    $this->actingAs($technician)
        ->get(route('edp.it-support-requests.index'))
        ->assertOk()
        ->assertSee($supportRequest->ticket_no)
        ->assertSee('Claim')
        ->assertDontSee('edp.it-support-requests.assign')
        ->assertDontSee('Reassign')
        ->assertDontSee('open</option>');

    $this->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Claim')
        ->assertDontSee('edp.it-support-requests.assign')
        ->assertDontSee('Priority');

    $this->actingAs($administrator)
        ->get(route('edp.it-support-requests.index'))
        ->assertOk()
        ->assertDontSee('edp.it-support-requests.assign')
        ->assertDontSee('Reassign')
        ->assertDontSee('name="technician_id"', false);
});

it('claims a submitted request atomically, records history, and notifies only the requester', function () {
    Notification::fake();
    $requester = User::factory()->create();
    $technician = requestTechnician(['first_name' => 'Taylor', 'last_name' => 'Tech']);
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create([
        'status' => ItSupportRequestStatus::Submitted,
        'assigned_technician_id' => null,
        'assigned_at' => null,
    ]);

    $this->actingAs($technician)
        ->patch(route('edp.it-support-requests.claim', $supportRequest))
        ->assertRedirect(route('edp.it-support-requests.index'));

    $supportRequest->refresh();
    $log = $supportRequest->logs()->where('action', 'claimed')->firstOrFail();

    expect($supportRequest->assigned_technician_id)->toBe($technician->getKey())
        ->and($supportRequest->assigned_at)->not->toBeNull()
        ->and($supportRequest->status)->toBe(ItSupportRequestStatus::Assigned)
        ->and($log->user_id)->toBe($technician->getKey())
        ->and($log->from_status)->toBe(ItSupportRequestStatus::Submitted->value)
        ->and($log->to_status)->toBe(ItSupportRequestStatus::Assigned->value)
        ->and($log->remarks)->toContain('Taylor Tech');

    Notification::assertSentTo($requester, RequestClaimed::class);
    Notification::assertNotSentTo($technician, RequestClaimed::class);
    $mail = (new RequestClaimed($supportRequest->fresh('technician')))->toMail($requester);
    expect($mail->introLines)->toContain('Taylor Tech is handling your request.');
});

it('allows only one technician to win a double-claim race and tells the loser who claimed it', function () {
    Notification::fake();
    $requester = User::factory()->create();
    $firstTechnician = requestTechnician(['first_name' => 'Alex', 'last_name' => 'Winner']);
    $secondTechnician = requestTechnician(['first_name' => 'Sam', 'last_name' => 'Loser']);
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($firstTechnician)
        ->patch(route('edp.it-support-requests.claim', $supportRequest))
        ->assertRedirect(route('edp.it-support-requests.index'));

    $this->actingAs($secondTechnician)
        ->from(route('it-support-requests.show', $supportRequest))
        ->patch(route('edp.it-support-requests.claim', $supportRequest))
        ->assertRedirect(route('it-support-requests.show', $supportRequest))
        ->assertSessionHas('error', 'Already claimed by Alex Winner');
    $this->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Already claimed by Alex Winner');

    expect($supportRequest->fresh()->assigned_technician_id)->toBe($firstTechnician->getKey())
        ->and($supportRequest->logs()->where('action', 'claimed')->count())->toBe(1);
    Notification::assertSentToTimes($requester, RequestClaimed::class, 1);
});

it('does not allow claims of cancelled, resolved, closed, or otherwise unavailable requests', function (ItSupportRequestStatus $status) {
    $technician = requestTechnician();
    $requester = User::factory()->create();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create([
        'status' => $status,
        'assigned_technician_id' => null,
    ]);

    $this->actingAs($technician)
        ->patch(route('edp.it-support-requests.claim', $supportRequest))
        ->assertRedirect()
        ->assertSessionHas('error', 'This request is no longer available to claim.');

    expect($supportRequest->fresh()->assigned_technician_id)->toBeNull()
        ->and($supportRequest->fresh()->status)->toBe($status);
})->with([
    'cancelled' => [ItSupportRequestStatus::Cancelled],
    'resolved' => [ItSupportRequestStatus::Resolved],
    'closed' => [ItSupportRequestStatus::Closed],
    'not submitted' => [ItSupportRequestStatus::Assigned],
]);

it('rejects claims by administrators, inactive EDP users, and non-EDP users', function (array $attributes) {
    $requester = User::factory()->create();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create();
    $user = requestTechnician($attributes);

    $this->actingAs($user)
        ->patch(route('edp.it-support-requests.claim', $supportRequest))
        ->assertForbidden();

    expect($supportRequest->fresh()->assigned_technician_id)->toBeNull();
})->with([
    'administrator' => [['role' => Role::Administrator]],
    'super administrator' => [['role' => Role::SuperAdministrator]],
    'inactive' => [['status' => UserStatus::Suspended]],
    'disabled' => [['is_active' => false]],
    'non-EDP' => [['division' => Division::CA]],
]);

it('lets a technician release their own claim with a required reason', function () {
    $requester = User::factory()->create();
    $technician = requestTechnician();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create([
        'assigned_technician_id' => $technician->getKey(),
        'assigned_at' => now(),
        'status' => ItSupportRequestStatus::Assigned,
    ]);

    $this->actingAs($technician)
        ->from(route('it-support-requests.show', $supportRequest))
        ->patch(route('edp.it-support-requests.release', $supportRequest), ['reason' => 'I am unavailable today.'])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    $supportRequest->refresh();
    $log = $supportRequest->logs()->where('action', 'released')->firstOrFail();
    expect($supportRequest->assigned_technician_id)->toBeNull()
        ->and($supportRequest->assigned_at)->toBeNull()
        ->and($supportRequest->started_at)->toBeNull()
        ->and($supportRequest->status)->toBe(ItSupportRequestStatus::Submitted)
        ->and($log->from_status)->toBe(ItSupportRequestStatus::Assigned->value)
        ->and($log->to_status)->toBe(ItSupportRequestStatus::Submitted->value)
        ->and($log->remarks)->toBe('I am unavailable today.');

    $this->patch(route('edp.it-support-requests.release', $supportRequest), [])
        ->assertSessionHasErrors('reason');
});

it('allows administrators to release any active claim but not claim requests themselves', function (Role $role) {
    $administrator = User::factory()->create(['role' => $role]);
    $technician = requestTechnician();
    $requester = User::factory()->create();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create([
        'assigned_technician_id' => $technician->getKey(),
        'assigned_at' => now(),
        'status' => ItSupportRequestStatus::InProgress,
    ]);
    $unclaimed = SupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.claim', $unclaimed))
        ->assertForbidden();

    $this->patch(route('edp.it-support-requests.release', $supportRequest), ['reason' => 'Rebalancing workload.'])
        ->assertRedirect();

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Submitted)
        ->and($supportRequest->fresh()->assigned_technician_id)->toBeNull();
})->with([
    'administrator' => [Role::Administrator],
    'super administrator' => [Role::SuperAdministrator],
]);

it('does not allow a technician to release another technician’s claim', function () {
    $requester = User::factory()->create();
    $owner = requestTechnician();
    $other = requestTechnician();
    $supportRequest = SupportRequest::factory()->for($requester, 'requester')->create([
        'assigned_technician_id' => $owner->getKey(),
        'status' => ItSupportRequestStatus::Assigned,
    ]);

    $this->actingAs($other)
        ->patch(route('edp.it-support-requests.release', $supportRequest), ['reason' => 'Not mine.'])
        ->assertForbidden();
});

it('renders legacy assignment and priority logs as plain text without rewriting them', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $supportRequest = SupportRequest::factory()->create();
    $assignmentLog = RequestLog::create([
        'requests_id' => $supportRequest->getKey(),
        'user_id' => $administrator->getKey(),
        'action' => 'assigned',
        'remarks' => 'Assigned to technician user #12.',
    ]);
    $priorityLog = RequestLog::create([
        'requests_id' => $supportRequest->getKey(),
        'user_id' => $administrator->getKey(),
        'action' => 'priority_changed',
        'remarks' => 'Priority changed from normal to urgent.',
    ]);

    $this->actingAs($administrator)
        ->get(route('activity-logs.index'))
        ->assertOk()
        ->assertSee('Recorded activity')
        ->assertSee('Assigned to technician user #12.')
        ->assertSee('Priority changed from normal to urgent.');

    expect($assignmentLog->fresh()->action)->toBe('assigned')
        ->and($priorityLog->fresh()->action)->toBe('priority_changed')
        ->and($priorityLog->fresh()->remarks)->toBe('Priority changed from normal to urgent.');
});
