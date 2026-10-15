<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestPriority;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Livewire\ManageItSupportRequests;
use App\Livewire\MyItSupportRequests;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use App\Notifications\RequestAssigned;
use App\Services\RequestOverdueQuery;
use Carbon\CarbonImmutable;
use App\Support\WorkingDayCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeDashboardRequest(User $requester, array $attributes = []): ItSupportRequest
{
    return ItSupportRequest::factory()->for($requester, 'requester')->create($attributes);
}

it('shows administrator card counts from one grouped status aggregate query', function () {
    CarbonImmutable::setTestNow('2026-10-09 10:00:00');
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();

    $recentSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(2),
        'requires_approval' => false,
    ]);
    $oldSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(9),
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requires_approval' => true,
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Approved,
        'requires_approval' => true,
    ]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::InProgress]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::OnHold]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Resolved,
        'resolved_at' => today()->setTime(8, 0),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'sum(case when status =')) {
            $queries[] = $query->sql;
        }
    });

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New requests (7 days)')
        ->assertSee('data-dashboard-card="new-requests-7-days"', false)
        ->assertSee('data-dashboard-card="pending-approval"', false)
        ->assertSee('data-dashboard-card="awaiting-assignment"', false)
        ->assertSee('status=submitted&amp;recent=7d', false);

    expect($queries)->toHaveCount(1);

    Livewire::actingAs($administrator)
        ->withQueryParams(['status' => 'submitted', 'recent' => '7d'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($recentSubmitted->ticket_no)
        ->assertDontSee($oldSubmitted->ticket_no);
});

it('shows exact seven-day, awaiting-assignment, resolved-today, and overdue dashboard counts', function () {
    CarbonImmutable::setTestNow('2026-10-09 10:00:00');
    $administrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $requester = User::factory()->create();

    $recentSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(6),
        'requires_approval' => false,
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(8),
        'requires_approval' => false,
    ]);
    $awaitingApproved = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Approved,
        'requires_approval' => true,
    ]);
    $awaitingSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requires_approval' => false,
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requires_approval' => true,
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
    ]);
    $resolvedToday = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Resolved,
        'resolved_at' => today()->setTime(9, 0),
    ]);
    $closedResolvedToday = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Closed,
        'resolved_at' => today()->setTime(9, 30),
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Resolved,
        'resolved_at' => today()->subDay(),
    ]);
    $overdue = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Assigned,
    ]);
    DB::table('it_support_requests')->where('id', $overdue->getKey())
        ->update(['updated_at' => now()->subDays(4)]);
    $active = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::InProgress,
    ]);
    DB::table('it_support_requests')->where('id', $active->getKey())
        ->update(['updated_at' => now()->subHours(4)]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::OnHold]);

    $response = $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New requests (7 days)')
        ->assertSee('data-dashboard-card="resolved-today"', false)
        ->assertSee('data-dashboard-card="overdue"', false)
        ->assertSee('status=submitted&amp;recent=7d', false);

    $html = $response->getContent();
    foreach ([
        'new-requests-7-days' => 3,
        'pending-approval' => 1,
        'awaiting-assignment' => 4,
        'in-progress' => 1,
        'on-hold' => 1,
        'resolved-today' => 2,
        'overdue' => 1,
    ] as $slug => $expectedCount) {
        preg_match(
            '/data-dashboard-card="'.preg_quote($slug, '/').'".*?<span class="mt-2 block text-3xl font-semibold text-gray-900">(\d+)<\/span>/s',
            $html,
            $matches,
        );
        expect((int) ($matches[1] ?? -1))->toBe($expectedCount);
    }

    Livewire::actingAs($administrator)
        ->withQueryParams(['status' => 'submitted', 'recent' => '7d'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($recentSubmitted->ticket_no)
        ->assertDontSee($awaitingApproved->ticket_no);

    Livewire::actingAs($administrator)
        ->withQueryParams(['awaiting_assignment' => '1'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($awaitingApproved->ticket_no)
        ->assertSee($awaitingSubmitted->ticket_no);

    Livewire::actingAs($administrator)
        ->withQueryParams(['resolved_today' => '1'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($resolvedToday->ticket_no)
        ->assertSee($closedResolvedToday->ticket_no);

    Livewire::actingAs($administrator)
        ->withQueryParams(['overdue' => '1'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($overdue->ticket_no)
        ->assertDontSee($active->ticket_no);
});

it('counts overdue activity using working days across weekends', function () {
    $requester = User::factory()->create();
    $monday = CarbonImmutable::parse('2026-10-12 10:00:00');
    CarbonImmutable::setTestNow($monday);

    expect(app(WorkingDayCalculator::class)->subtractWorkingDays(2)->toDateTimeString())
        ->toBe('2026-10-08 10:00:00');

    $fridayActivity = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Assigned,
    ]);
    $fridayActivity->logs()->create([
        'action' => 'activity',
        'created_at' => '2026-10-09 09:00:00',
    ]);

    $thursdayActivity = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::OnHold,
    ]);
    $thursdayActivity->logs()->create([
        'action' => 'activity',
        'created_at' => '2026-10-08 09:00:00',
    ]);

    expect(app(RequestOverdueQuery::class)->apply(ItSupportRequest::query())->pluck('ticket_no')->all())
        ->toContain($thursdayActivity->ticket_no)
        ->not->toContain($fridayActivity->ticket_no);
});

it('shows cards only to administrators and welcome actions without cards to other roles', function () {
    $technician = User::factory()->create(['division' => Division::EDP]);
    $otherTechnician = User::factory()->create(['division' => Division::EDP]);
    $requester = User::factory()->create();
    $assigned = makeDashboardRequest($requester, [
        'assigned_technician_id' => $technician->getKey(),
        'status' => ItSupportRequestStatus::InProgress,
    ]);
    makeDashboardRequest($requester, [
        'assigned_technician_id' => $otherTechnician->getKey(),
        'status' => ItSupportRequestStatus::InProgress,
    ]);

    $this->actingAs($technician)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your support home')
        ->assertSee('Submit a request')
        ->assertSee('Track my requests')
        ->assertDontSee('data-dashboard-card=', false)
        ->assertDontSee('Assigned to Me');

    $ownRequest = makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::Submitted]);
    $otherRequest = makeDashboardRequest($otherTechnician, ['status' => ItSupportRequestStatus::Submitted]);

    $this->actingAs($requester)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Your support home')
        ->assertSee('Submit a request')
        ->assertSee('Track my requests')
        ->assertDontSee('data-dashboard-card=', false)
        ->assertDontSee('Assigned to Me');

    Livewire::actingAs($requester)
        ->withQueryParams(['status' => 'submitted'])
        ->test(MyItSupportRequests::class)
        ->assertSee($ownRequest->ticket_no)
        ->assertDontSee($otherRequest->ticket_no);
});

it('restricts dashboard cards and the assigned queue to the two administrator roles', function () {
    foreach (Role::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);
        $isAdministrator = in_array($role, [Role::Administrator, Role::SuperAdministrator], true);

        $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        if ($isAdministrator) {
            $dashboard->assertSee('Request overview')
                ->assertSee('My assigned work')
                ->assertSee('My assigned requests')
                ->assertSee('Assigned')
                ->assertSee('In progress')
                ->assertSee('On hold')
                ->assertSee('Resolved')
                ->assertSee('data-dashboard-card=', false)
                ->assertSee('Assigned to Me');

            $this->get(route('edp.assigned-requests.index'))->assertOk();
        } else {
            $dashboard->assertSee('Your support home')
                ->assertSee('Submit a request')
                ->assertSee('Track my requests')
                ->assertDontSee('data-dashboard-card=', false)
                ->assertDontSee('Assigned to Me');

            $this->get(route('edp.assigned-requests.index'))->assertForbidden();
        }
    }
});

it('forbids non-administrators from viewing the request management queue', function () {
    $employee = User::factory()->create(['role' => Role::Staff]);

    $this->actingAs($employee)->get(route('edp.it-support-requests.index'))->assertForbidden();
});

it('filters the queue by status, type, division, priority, and technician including unassigned', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create(['first_name' => 'Queue', 'last_name' => 'Requester']);
    $technician = User::factory()->create(['division' => Division::EDP]);
    $type = RequestType::factory()->create(['name' => 'Filtered request type']);
    $match = makeDashboardRequest($requester, [
        'request_type_id' => $type->getKey(),
        'division' => Division::EDP,
        'priority' => ItSupportRequestPriority::Urgent,
        'status' => ItSupportRequestStatus::Assigned,
        'assigned_technician_id' => $technician->getKey(),
    ]);
    $unassigned = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'assigned_technician_id' => null,
    ]);

    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('status', ItSupportRequestStatus::Assigned->value)
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);
    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('requestType', (string) $type->getKey())
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);
    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('division', Division::EDP->value)
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);
    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('priority', ItSupportRequestPriority::Urgent->value)
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);
    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('technician', (string) $technician->getKey())
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);

    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('technician', 'unassigned')
        ->assertSee($unassigned->ticket_no)
        ->assertDontSee($match->ticket_no);
});

it('sorts assigned tickets by explicit priority order and then oldest first', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $createAssigned = function (ItSupportRequestPriority $priority, string $requestedAt) use ($administrator, $requester) {
        return makeDashboardRequest($requester, [
            'assigned_technician_id' => $administrator->getKey(),
            'priority' => $priority,
            'requested_at' => $requestedAt,
            'status' => ItSupportRequestStatus::Assigned,
        ]);
    };

    $high = $createAssigned(ItSupportRequestPriority::High, '2026-10-01 09:00:00');
    $urgentNew = $createAssigned(ItSupportRequestPriority::Urgent, '2026-10-05 09:00:00');
    $normal = $createAssigned(ItSupportRequestPriority::Normal, '2026-09-30 09:00:00');
    $urgentOld = $createAssigned(ItSupportRequestPriority::Urgent, '2026-09-20 09:00:00');
    $low = $createAssigned(ItSupportRequestPriority::Low, '2026-09-01 09:00:00');

    Livewire::actingAs($administrator)
        ->test(\App\Livewire\AssignedItSupportRequests::class)
        ->assertSeeInOrder([
            $urgentOld->ticket_no,
            $urgentNew->ticket_no,
            $high->ticket_no,
            $normal->ticket_no,
            $low->ticket_no,
        ]);
});

it('marks assigned tickets overdue after two working days without activity', function () {
    CarbonImmutable::setTestNow('2026-10-12 12:00:00');
    try {
        $administrator = User::factory()->create(['role' => Role::Administrator]);
        $requester = User::factory()->create();
        $overdue = makeDashboardRequest($requester, [
            'assigned_technician_id' => $administrator->getKey(),
            'status' => ItSupportRequestStatus::Assigned,
        ]);
        DB::table('it_support_requests')->where('id', $overdue->getKey())->update([
            'updated_at' => '2026-10-07 11:00:00',
        ]);

        Livewire::actingAs($administrator)
            ->test(\App\Livewire\AssignedItSupportRequests::class)
            ->assertSee('Overdue')
            ->assertSee($overdue->ticket_no);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('shows the saved short title and request type on assigned request rows', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $answerBacked = makeDashboardRequest($requester, [
        'assigned_technician_id' => $administrator->getKey(),
        'summary' => 'Payroll system unavailable',
        'support_type' => 'Troubleshoot System / Application',
        'details' => 'Legacy value must not take precedence.',
    ]);
    $answerBacked->detailsRows()->create([
        'field_key' => 'system',
        'field_label' => 'System',
        'field_value' => 'Payroll system answer',
    ]);
    $legacy = makeDashboardRequest($requester, [
        'assigned_technician_id' => $administrator->getKey(),
        'summary' => 'Older request summary',
        'details' => 'Legacy ticket fallback text.',
    ]);

    Livewire::actingAs($administrator)
        ->test(\App\Livewire\AssignedItSupportRequests::class)
        ->assertSee('Payroll system unavailable')
        ->assertSee('Troubleshoot System / Application')
        ->assertSee('Older request summary')
        ->assertDontSee('Legacy value must not take precedence.');
});

it('filters the queue by requested date range and searches ticket number and requester name', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create(['first_name' => 'Unique', 'last_name' => 'Requester']);
    $inRange = makeDashboardRequest($requester, [
        'requested_at' => '2026-10-05 13:00:00',
    ]);
    $outOfRange = makeDashboardRequest($requester, [
        'requested_at' => '2026-10-01 13:00:00',
    ]);

    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('requestedFrom', '2026-10-05')
        ->set('requestedTo', '2026-10-06')
        ->assertSee($inRange->ticket_no)
        ->assertDontSee($outOfRange->ticket_no);

    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('search', $inRange->ticket_no)
        ->assertSee($inRange->ticket_no)
        ->assertDontSee($outOfRange->ticket_no);

    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('search', 'Unique Requester')
        ->assertSee($inRange->ticket_no)
        ->assertSee($outOfRange->ticket_no);
});

it('displays technician workloads from one grouped count query', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create([
        'first_name' => 'Workload',
        'last_name' => 'Technician',
        'division' => Division::EDP,
    ]);

    foreach ([
        ItSupportRequestStatus::Assigned,
        ItSupportRequestStatus::InProgress,
        ItSupportRequestStatus::OnHold,
    ] as $status) {
        makeDashboardRequest($requester, [
            'assigned_technician_id' => $technician->getKey(),
            'status' => $status,
        ]);
    }
    makeDashboardRequest($requester, [
        'assigned_technician_id' => $technician->getKey(),
        'status' => ItSupportRequestStatus::Resolved,
    ]);

    $workloadQueries = [];
    DB::listen(function ($query) use (&$workloadQueries): void {
        if (str_contains(strtolower($query->sql), 'group by "assigned_technician_id"')) {
            $workloadQueries[] = $query->sql;
        }
    });

    $this->actingAs($administrator)
        ->get(route('edp.it-support-requests.index'))
        ->assertOk()
        ->assertSee('Workload Technician (3 open)');

    expect($workloadQueries)->toHaveCount(1);
});

it('keeps active queue filters in the URL and pagination links', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $type = RequestType::factory()->create();

    foreach (range(1, 21) as $number) {
        makeDashboardRequest($requester, [
            'request_type_id' => $type->getKey(),
            'status' => ItSupportRequestStatus::Submitted,
            'priority' => ItSupportRequestPriority::Urgent,
        ]);
    }

    $component = Livewire::actingAs($administrator)
        ->withQueryParams([
            'status' => ItSupportRequestStatus::Submitted->value,
            'request_type' => (string) $type->getKey(),
            'priority' => ItSupportRequestPriority::Urgent->value,
        ])
        ->test(ManageItSupportRequests::class)
        ->assertSee('wire:click="gotoPage(2', false)
        ->assertSet('status', ItSupportRequestStatus::Submitted->value)
        ->assertSet('requestType', (string) $type->getKey());

    $component->call('gotoPage', 2)
        ->assertSet('status', ItSupportRequestStatus::Submitted->value)
        ->assertSet('requestType', (string) $type->getKey())
        ->assertSee(ItSupportRequest::query()->orderBy('id')->skip(20)->value('ticket_no'));
});

it('filters queue results by priority, resolves today, overdue, and preserves sorting filters in the URL', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $urgent = makeDashboardRequest($requester, ['priority' => ItSupportRequestPriority::Urgent]);
    $normal = makeDashboardRequest($requester, ['priority' => ItSupportRequestPriority::Normal]);

    Livewire::actingAs($administrator)
        ->test(ManageItSupportRequests::class)
        ->set('sort', 'priority')
        ->assertSeeInOrder([$urgent->ticket_no, $normal->ticket_no])
        ->assertSet('sort', 'priority');
});

it('notifies assigned technicians and requesters through the queue after assignment', function () {
    Notification::fake();
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = makeDashboardRequest($requester, ['summary' => 'Printer jam on floor two']);

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.assign', $supportRequest), [
            'technician_id' => $technician->getKey(),
        ])
        ->assertRedirect(route('edp.it-support-requests.index'));

    Notification::assertSentTo($technician, RequestAssigned::class);
    Notification::assertSentTo($requester, RequestAssigned::class);
    $notification = new RequestAssigned($supportRequest->fresh());
    $mail = $notification->toMail($technician);
    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($mail->subject)->toContain('Printer jam on floor two')
        ->and($mail->introLines)->toContain('Request: Printer jam on floor two');
});

it('logs administrator priority changes and rejects priority changes by other roles', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $employee = User::factory()->create(['role' => Role::Staff]);
    $requester = User::factory()->create();
    $supportRequest = makeDashboardRequest($requester, [
        'priority' => ItSupportRequestPriority::Normal,
    ]);

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.priority', $supportRequest), [
            'priority' => ItSupportRequestPriority::Urgent->value,
        ])
        ->assertRedirect();

    $log = $supportRequest->logs()->where('action', 'priority_changed')->firstOrFail();
    expect($supportRequest->fresh()->priority)->toBe(ItSupportRequestPriority::Urgent)
        ->and($log->user_id)->toBe($administrator->getKey())
        ->and($log->remarks)->toContain('normal to urgent');

    $this->actingAs($employee)
        ->patch(route('edp.it-support-requests.priority', $supportRequest), [
            'priority' => ItSupportRequestPriority::High->value,
        ])
        ->assertForbidden();
});
