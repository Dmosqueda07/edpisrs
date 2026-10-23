<?php

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function activityLogUser(Role $role, array $attributes = []): User
{
    return User::factory()->create(array_merge(['role' => $role], $attributes));
}

it('allows both administrator roles to view request logs and denies other or inactive accounts', function () {
    foreach ([Role::Administrator, Role::SuperAdministrator] as $role) {
        $this->actingAs(activityLogUser($role))->get(route('activity-logs.index'))->assertOk();
    }

    $this->actingAs(activityLogUser(Role::Staff))->get(route('activity-logs.index'))->assertForbidden();
    $this->actingAs(activityLogUser(Role::Administrator, ['status' => UserStatus::Suspended, 'is_active' => false]))
        ->get(route('activity-logs.index'))->assertForbidden();
});

it('shows the accounts tab only to super administrators', function () {
    $this->actingAs(activityLogUser(Role::Administrator))
        ->get(route('activity-logs.index', ['tab' => 'accounts']))->assertForbidden();

    $this->actingAs(activityLogUser(Role::SuperAdministrator))
        ->get(route('activity-logs.index', ['tab' => 'accounts']))
        ->assertOk()->assertSee('Earlier actions remain in the daily audit log files');
});

it('lists request log entries newest first', function () {
    $admin = activityLogUser(Role::Administrator);
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();
    $olderActor = User::factory()->create(['first_name' => 'Older', 'last_name' => 'Actor']);
    $newerActor = User::factory()->create(['first_name' => 'Newer', 'last_name' => 'Actor']);
    ItSupportRequestLog::factory()->create(['it_support_request_id' => $supportRequest->id, 'user_id' => $olderActor->user_id, 'action' => 'assigned', 'created_at' => '2026-06-01 10:00:00']);
    ItSupportRequestLog::factory()->create(['it_support_request_id' => $supportRequest->id, 'user_id' => $newerActor->user_id, 'action' => 'resolved', 'created_at' => '2026-06-02 10:00:00']);

    $this->actingAs($admin)->get(route('activity-logs.index'))
        ->assertOk()->assertSeeInOrder(['Newer Actor', 'Marked as resolved', 'Older Actor', 'Assigned the request']);
});

it('filters request activity by ticket or person, date range, and action while retaining filters in pagination', function () {
    $admin = activityLogUser(Role::Administrator);
    $requester = User::factory()->create();
    $ticketMatch = ItSupportRequest::factory()->for($requester, 'requester')->create(['ticket_no' => 'EDP-2026-11111']);
    $ticketOther = ItSupportRequest::factory()->for($requester, 'requester')->create(['ticket_no' => 'EDP-2026-22222']);
    $actor = User::factory()->create(['first_name' => 'Filterable', 'last_name' => 'Person']);
    ItSupportRequestLog::factory()->create(['it_support_request_id' => $ticketMatch->id, 'user_id' => $actor->user_id, 'action' => 'resolved', 'created_at' => '2026-06-15 10:00:00']);
    foreach (range(1, 25) as $minute) {
        ItSupportRequestLog::factory()->create([
            'it_support_request_id' => $ticketMatch->id, 'user_id' => $actor->user_id, 'action' => 'resolved',
            'created_at' => sprintf('2026-06-15 11:%02d:00', $minute - 1),
        ]);
    }
    ItSupportRequestLog::factory()->create(['it_support_request_id' => $ticketOther->id, 'user_id' => $actor->user_id, 'action' => 'assigned', 'created_at' => '2026-06-15 10:00:00']);
    ItSupportRequestLog::factory()->create(['it_support_request_id' => $ticketMatch->id, 'user_id' => $actor->user_id, 'action' => 'resolved', 'created_at' => '2026-07-01 00:00:00']);

    $this->actingAs($admin)->get(route('activity-logs.index', [
        'search' => 'EDP-2026-11111', 'start' => '2026-06-01', 'end' => '2026-06-30', 'action' => 'resolved',
    ]))->assertOk()->assertSee('EDP-2026-11111')->assertDontSee('EDP-2026-22222')->assertSee('Marked as resolved')
        ->assertSee('search=EDP-2026-11111', false)->assertSee('action=resolved', false);

    $this->get(route('activity-logs.index', [
        'search' => 'Filterable', 'start' => '2026-06-01', 'end' => '2026-06-30', 'action' => 'assigned',
    ]))->assertOk()->assertSee('EDP-2026-22222')->assertDontSee('EDP-2026-11111');
});

it('shows a clear empty state when no request activity matches', function () {
    $this->actingAs(activityLogUser(Role::Administrator))
        ->get(route('activity-logs.index', ['search' => 'not-a-ticket']))
        ->assertOk()->assertSee('No activity matches these filters');
});
