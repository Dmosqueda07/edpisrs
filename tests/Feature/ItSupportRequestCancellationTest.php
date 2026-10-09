<?php

use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Models\ItSupportRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a requester to cancel submitted or pending approval requests and audits each cancellation', function (ItSupportRequestStatus $status) {
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create(['status' => $status]);

    $this->actingAs($requester)
        ->patch(route('it-support-requests.cancel', $supportRequest), ['reason' => 'No longer needed.'])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Cancelled)
        ->and($supportRequest->logs()->where('action', 'cancelled')->value('from_status'))->toBe($status->value)
        ->and($supportRequest->logs()->where('action', 'cancelled')->value('remarks'))->toBe('No longer needed.');
})->with([
    'submitted' => [ItSupportRequestStatus::Submitted],
    'pending approval' => [ItSupportRequestStatus::PendingApproval],
]);

it('allows an administrator to cancel an unresolved request and denies cancellation by another requester', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()
        ->for($owner, 'requester')
        ->create(['status' => ItSupportRequestStatus::InProgress]);

    $this->actingAs($otherUser)
        ->patch(route('it-support-requests.cancel', $supportRequest))
        ->assertForbidden();

    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $this->actingAs($administrator)
        ->patch(route('it-support-requests.cancel', $supportRequest), ['reason' => 'Duplicate request.'])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Cancelled)
        ->and($supportRequest->logs()->where('action', 'cancelled')->value('user_id'))->toBe($administrator->user_id);
});

it('does not allow cancellation after resolution or repeated cancellation', function () {
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'status' => ItSupportRequestStatus::Resolved,
        'resolved_at' => now(),
    ]);

    $this->actingAs($requester)
        ->patch(route('it-support-requests.cancel', $supportRequest))
        ->assertForbidden();

    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $this->actingAs($administrator)
        ->patch(route('it-support-requests.cancel', $supportRequest))
        ->assertForbidden();
});

it('allows the requester to reopen a resolved request within three days with a reason and the same technician', function () {
    Carbon::setTestNow('2026-10-08 10:00:00');
    try {
        $requester = User::factory()->create();
        $technician = User::factory()->create();
        $supportRequest = ItSupportRequest::factory()
            ->for($requester, 'requester')
            ->create([
                'assigned_technician_id' => $technician->user_id,
                'status' => ItSupportRequestStatus::Resolved,
                'resolved_at' => now()->subDays(2),
                'resolved_by_user_id' => $technician->user_id,
            ]);

        $this->actingAs($requester)
            ->patch(route('it-support-requests.reopen', $supportRequest), ['reason' => 'The issue returned.'])
            ->assertRedirect(route('it-support-requests.show', $supportRequest));

        $reopenLog = $supportRequest->logs()->where('action', 'reopened')->firstOrFail();
        expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Assigned)
            ->and($supportRequest->fresh()->assigned_technician_id)->toBe($technician->user_id)
            ->and($supportRequest->fresh()->resolved_at)->toBeNull()
            ->and($reopenLog->from_status)->toBe(ItSupportRequestStatus::Resolved->value)
            ->and($reopenLog->to_status)->toBe(ItSupportRequestStatus::Assigned->value)
            ->and($reopenLog->remarks)->toBe('The issue returned.');
    } finally {
        Carbon::setTestNow();
    }
});

it('requires a reopen reason and denies other users or requests outside three days', function () {
    Carbon::setTestNow('2026-10-08 10:00:00');
    try {
        $requester = User::factory()->create();
        $otherUser = User::factory()->create();
        $technician = User::factory()->create();
        $recentRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
            'assigned_technician_id' => $technician->user_id,
            'status' => ItSupportRequestStatus::Resolved,
            'resolved_at' => now()->subDay(),
        ]);
        $expiredRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
            'assigned_technician_id' => $technician->user_id,
            'status' => ItSupportRequestStatus::Resolved,
            'resolved_at' => now()->subDays(3)->subSecond(),
        ]);

        $this->actingAs($requester)
            ->patch(route('it-support-requests.reopen', $recentRequest))
            ->assertSessionHasErrors('reason');

        $this->actingAs($otherUser)
            ->patch(route('it-support-requests.reopen', $recentRequest), ['reason' => 'Wrong requester.'])
            ->assertForbidden();

        $this->actingAs($requester)
            ->patch(route('it-support-requests.reopen', $expiredRequest), ['reason' => 'Too late.'])
            ->assertForbidden();
    } finally {
        Carbon::setTestNow();
    }
});
