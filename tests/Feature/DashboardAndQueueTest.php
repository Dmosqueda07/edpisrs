<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Livewire\AssignedItSupportRequests;
use App\Livewire\ManageItSupportRequests;
use App\Livewire\MyItSupportRequests;
use App\Models\RequestLog;
use App\Models\RequestType;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\ItSupportRequestAuditLogger;
use App\Services\RequestDashboardMetrics;
use App\Services\RequestOverdueQuery;
use App\Support\WorkingDayCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function makeDashboardRequest(User $requester, array $attributes = []): SupportRequest
{
    return SupportRequest::factory()->for($requester, 'requester')->create($attributes);
}

it('shows administrator card counts from one grouped status aggregate query', function () {
    CarbonImmutable::setTestNow('2026-10-09 10:00:00');
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();

    $recentSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(2),
    ]);
    $oldSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(9),
    ]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::Submitted]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::InProgress]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::OnHold]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Resolved,
        'resolved_at' => today()->setTime(8, 0),
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains(strtolower($query->sql), 'sum(case when support_requests.status =')) {
            $queries[] = $query->sql;
        }
    });

    $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New requests (7 days)')
        ->assertSee('data-dashboard-card="new-requests-7-days"', false)
        ->assertDontSee('data-dashboard-card="pending-approval"', false)
        ->assertSee('data-dashboard-card="available-to-claim"', false)
        ->assertSee('tab=submitted&amp;recent=7d', false);

    expect($queries)->toHaveCount(1);

    Livewire::actingAs($administrator)
        ->withQueryParams(['tab' => 'submitted', 'recent' => '7d'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($recentSubmitted->ticket_no)
        ->assertDontSee($oldSubmitted->ticket_no);
});

it('defaults the intake queue to Submitted and counts filtered statuses in one grouped query', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $submitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
    ]);
    $assigned = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Assigned,
    ]);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Assigned,
    ]);
    $cancelled = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Cancelled,
    ]);

    $statusCountQueries = [];
    DB::listen(function ($query) use (&$statusCountQueries): void {
        $sql = strtolower($query->sql);
        if (str_contains($sql, 'request_count') && str_contains($sql, 'group by') && str_contains($sql, 'status')) {
            $statusCountQueries[] = $query->sql;
        }
    });

    Livewire::actingAs($administrator)
        ->test(ManageItSupportRequests::class)
        ->assertSet('status', ItSupportRequestStatus::Submitted->value)
        ->assertSeeInOrder([
            'data-status-tab="all"',
            'data-status-tab="submitted"',
            'data-status-tab="assigned"',
        ], false)
        ->assertSee($submitted->ticket_no)
        ->assertDontSee($assigned->ticket_no)
        ->assertSee('data-status-tab="submitted" data-status-count="1"', false)
        ->assertSee('data-status-tab="assigned" data-status-count="2"', false)
        ->assertSee('data-status-tab="cancelled" data-status-count="1"', false)
        ->assertSee('data-status-tab="all" data-status-count="4"', false);

    expect($statusCountQueries)->toHaveCount(1);
});

it('uses only the explicit tab query parameter and keeps Submitted as the default', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);

    Livewire::actingAs($administrator)
        ->withQueryParams(['status' => ItSupportRequestStatus::Assigned->value])
        ->test(ManageItSupportRequests::class)
        ->assertSet('status', ItSupportRequestStatus::Submitted->value);

    Livewire::actingAs($administrator)
        ->withQueryParams(['tab' => ItSupportRequestStatus::Assigned->value, 'status' => ItSupportRequestStatus::Submitted->value])
        ->test(ManageItSupportRequests::class)
        ->assertSet('status', ItSupportRequestStatus::Assigned->value);
});

it('shows exact seven-day, available-to-claim, resolved-today, and overdue dashboard counts', function () {
    CarbonImmutable::setTestNow('2026-10-09 10:00:00');
    $administrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $requester = User::factory()->create();

    $recentSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(6),
    ]);
    $oldSubmitted = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(8),
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
        'requested_at' => now()->subDays(4),
    ]);
    $active = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::InProgress,
    ]);
    makeDashboardRequest($requester, ['status' => ItSupportRequestStatus::OnHold]);

    $response = $this->actingAs($administrator)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('New requests (7 days)')
        ->assertSee('data-dashboard-card="resolved-today"', false)
        ->assertSee('data-dashboard-card="overdue"', false)
        ->assertSee('tab=submitted&amp;recent=7d', false);

    $html = $response->getContent();
    $expectedCards = [
        'New requests (7 days)' => 1,
        'Available to claim' => 2,
        'In progress' => 1,
        'On hold' => 1,
        'Resolved today' => 2,
        'Overdue' => 3,
    ];
    $actualCards = collect(app(RequestDashboardMetrics::class)->administratorDashboard($administrator)['overview'])
        ->pluck('count', 'label');
    expect($actualCards->all())->toMatchArray($expectedCards);

    foreach (array_keys($expectedCards) as $label) {
        expect($html)->toContain('data-dashboard-card="'.Str::slug($label).'"');
    }

    Livewire::actingAs($administrator)
        ->withQueryParams(['tab' => 'submitted', 'recent' => '7d'])
        ->test(ManageItSupportRequests::class)
        ->assertSee($recentSubmitted->ticket_no)
        ->assertDontSee($oldSubmitted->ticket_no);

    Livewire::actingAs($administrator)
        ->withQueryParams(['awaiting_claim' => '1'])
        ->test(ManageItSupportRequests::class)
        ->set('status', '')
        ->assertSee($recentSubmitted->ticket_no)
        ->assertSee($oldSubmitted->ticket_no);

    Livewire::actingAs($administrator)
        ->withQueryParams(['resolved_today' => '1'])
        ->test(ManageItSupportRequests::class)
        ->set('status', '')
        ->assertSee($resolvedToday->ticket_no)
        ->assertSee($closedResolvedToday->ticket_no);

    Livewire::actingAs($administrator)
        ->withQueryParams(['overdue' => '1'])
        ->test(ManageItSupportRequests::class)
        ->set('status', '')
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
        'requested_at' => '2026-10-09 09:00:00',
    ]);
    $fridayActivity->logs()->create([
        'action' => 'activity',
        'created_at' => '2026-10-09 09:00:00',
    ]);

    $thursdayActivity = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::OnHold,
        'requested_at' => '2026-10-08 09:00:00',
    ]);
    $thursdayActivity->logs()->create([
        'action' => 'activity',
        'created_at' => '2026-10-08 09:00:00',
    ]);

    expect(app(RequestOverdueQuery::class)->apply(SupportRequest::query())->pluck('ticket_no')->all())
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

it('restricts the redesigned dashboard and the assigned queue to the two administrator roles', function () {
    foreach (Role::cases() as $role) {
        $user = User::factory()->create(['role' => $role]);
        $isAdministrator = in_array($role, [Role::Administrator, Role::SuperAdministrator], true);

        $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk();
        if ($isAdministrator) {
            $dashboard->assertSee('Request overview')
                ->assertSee('Operational shortcuts')
                ->assertSee('User management')
                ->assertSee('EDP intake')
                ->assertSee('Reports')
                ->assertSee('Activity logs')
                ->assertDontSee('My assigned work')
                ->assertSee('data-dashboard-card=', false)
                ->assertDontSee('What can we');

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

it('shows administrator shortcut totals, hides the assigned line at zero, and has useful empty states', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    User::factory()->create(['status' => 'pending']);
    User::factory()->create(['status' => 'suspended']);
    makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'requested_at' => now()->subDays(2),
    ]);

    $this->actingAs($administrator)->get(route('dashboard'))->assertOk()
        ->assertSeeText('4 total users')
        ->assertSee('1 suspended')
        ->assertSee('No request activity has been recorded yet.')
        ->assertDontSee('You have 0 assigned requests')
        ->assertSee('No matching requests');
});

it('calculates grouped shortcut totals and monthly report activity', function () {
    CarbonImmutable::setTestNow('2026-10-15 12:00:00');
    try {
        $administrator = User::factory()->create(['role' => Role::Administrator]);
        $requester = User::factory()->create();
        User::factory()->create(['status' => 'pending']);
        User::factory()->create(['status' => 'suspended']);

        makeDashboardRequest($requester, [
            'status' => ItSupportRequestStatus::Submitted,
            'requested_at' => '2026-10-13 12:00:00',
        ]);
        $resolved = makeDashboardRequest($requester, [
            'status' => ItSupportRequestStatus::Resolved,
            'requested_at' => '2026-10-10 10:00:00',
            'resolved_at' => '2026-10-14 10:00:00',
        ]);
        makeDashboardRequest($requester, [
            'status' => ItSupportRequestStatus::Submitted,
            'requested_at' => '2026-10-15 09:00:00',
        ]);
        RequestLog::factory()->create([
            'requests_id' => $resolved->requests_id,
            'user_id' => $administrator->user_id,
            'action' => 'resolved',
            'created_at' => '2026-10-14 10:00:00',
        ]);

        $dashboard = app(RequestDashboardMetrics::class)->administratorDashboard($administrator);
        expect($dashboard['shortcuts']['users']['total'])->toBe(4)
            ->and($dashboard['shortcuts']['users']['pending'])->toBe(1)
            ->and($dashboard['shortcuts']['users']['suspended'])->toBe(1)
            ->and($dashboard['shortcuts']['intake']['awaiting'])->toBe(2)
            ->and($dashboard['shortcuts']['intake']['overdue'])->toBe(0)
            ->and($dashboard['shortcuts']['reports']['received'])->toBe(3)
            ->and($dashboard['shortcuts']['reports']['resolved'])->toBe(1)
            ->and($dashboard['shortcuts']['activity']['entries'])->toHaveCount(1)
            ->and($dashboard['shortcuts']['activity']['entries']->first()->supportRequest->ticket_no)->toBe($resolved->ticket_no);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('shows the admin assigned request line only for requests assigned to that admin', function () {
    $administrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    makeDashboardRequest(User::factory()->create(), ['assigned_technician_id' => $administrator->getKey(), 'status' => ItSupportRequestStatus::Assigned]);

    $this->actingAs($administrator)->get(route('dashboard'))->assertOk()
        ->assertSee('You have 1 assigned request')
        ->assertSee('View assigned to me');
});

it('shows the saved actor name for new dashboard activity and labels null actors as System', function () {
    $administrator = User::factory()->create([
        'role' => Role::Administrator,
        'first_name' => 'Audit',
        'last_name' => 'Operator',
    ]);
    $requester = User::factory()->create();
    $supportRequest = makeDashboardRequest($requester);
    $audit = app(ItSupportRequestAuditLogger::class);
    $audit->record($supportRequest, $administrator, 'assigned', ItSupportRequestStatus::Submitted, ItSupportRequestStatus::Assigned);
    $audit->record($supportRequest, null, 'work_started', ItSupportRequestStatus::Assigned, ItSupportRequestStatus::InProgress);

    expect(DB::table('request_logs')->where('action', 'assigned')->value('user_id'))
        ->toBe($administrator->user_id);

    $this->actingAs($administrator)->get(route('dashboard'))->assertOk()
        ->assertSee('Audit Operator')
        ->assertSee('System')
        ->assertDontSee('Unknown user');
});

it('allows active EDP technicians and administrators to view the intake queue', function () {
    $technician = User::factory()->create(['role' => Role::Staff, 'division' => Division::EDP]);
    $employee = User::factory()->create(['role' => Role::Staff, 'division' => Division::CA]);

    $this->actingAs($technician)->get(route('edp.it-support-requests.index'))->assertOk();
    $this->actingAs($employee)->get(route('edp.it-support-requests.index'))->assertForbidden();
});

it('filters the queue by status, type, division, and technician including unassigned', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create(['first_name' => 'Queue', 'last_name' => 'Requester']);
    $technician = User::factory()->create(['division' => Division::EDP]);
    $type = RequestType::factory()->create(['name' => 'Filtered request type']);
    $match = makeDashboardRequest($requester, [
        'request_types_id' => $type->getKey(),
        'division' => Division::EDP,
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
        ->set('status', '')
        ->set('requestType', (string) $type->getKey())
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);
    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('status', '')
        ->set('division', Division::EDP->value)
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);
    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('status', '')
        ->set('technician', (string) $technician->getKey())
        ->assertSee($match->ticket_no)
        ->assertDontSee($unassigned->ticket_no);

    Livewire::actingAs($administrator)->test(ManageItSupportRequests::class)
        ->set('status', '')
        ->set('technician', 'unassigned')
        ->assertSee($unassigned->ticket_no)
        ->assertDontSee($match->ticket_no);
});

it('always includes request type requester and division in secondary metadata when title falls back to type', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $type = RequestType::factory()->create(['name' => 'Password reset']);
    $supportRequest = makeDashboardRequest($requester, [
        'status' => ItSupportRequestStatus::Submitted,
        'request_types_id' => $type->getKey(),
        'support_type' => $type->name,
        'summary' => $type->name,
        'requester_name' => 'Queue metadata requester',
        'division' => Division::EDP,
    ]);

    Livewire::actingAs($administrator)
        ->test(ManageItSupportRequests::class)
        ->assertSeeInOrder([
            $type->name,
            $type->name,
            $supportRequest->requester_name,
            Division::EDP->value,
        ]);
});

it('orders assigned tickets oldest first', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $createAssigned = function (string $requestedAt) use ($administrator, $requester) {
        return makeDashboardRequest($requester, [
            'assigned_technician_id' => $administrator->getKey(),
            'requested_at' => $requestedAt,
            'status' => ItSupportRequestStatus::Assigned,
        ]);
    };

    $newest = $createAssigned('2026-10-05 09:00:00');
    $older = $createAssigned('2026-10-01 09:00:00');
    $oldest = $createAssigned('2026-09-20 09:00:00');

    Livewire::actingAs($administrator)
        ->test(AssignedItSupportRequests::class)
        ->assertSeeInOrder([
            $oldest->ticket_no,
            $older->ticket_no,
            $newest->ticket_no,
        ]);
});

it('marks assigned tickets overdue based on request age even after recent activity', function () {
    CarbonImmutable::setTestNow('2026-10-12 12:00:00');
    try {
        $administrator = User::factory()->create(['role' => Role::Administrator]);
        $requester = User::factory()->create();
        $overdue = makeDashboardRequest($requester, [
            'assigned_technician_id' => $administrator->getKey(),
            'status' => ItSupportRequestStatus::Assigned,
            'requested_at' => '2026-10-07 11:00:00',
        ]);
        DB::table('support_requests')->where('requests_id', $overdue->getKey())->update([
            'updated_at' => '2026-10-12 11:00:00',
        ]);

        Livewire::actingAs($administrator)
            ->test(AssignedItSupportRequests::class)
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
        'answers' => ['system' => 'Payroll system answer'],
    ]);
    $legacy = makeDashboardRequest($requester, [
        'assigned_technician_id' => $administrator->getKey(),
        'summary' => 'Older request summary',
    ]);

    Livewire::actingAs($administrator)
        ->test(AssignedItSupportRequests::class)
        ->assertSee('Payroll system unavailable')
        ->assertSee('Troubleshoot System / Application')
        ->assertSee('Older request summary')
        ->assertDontSee('Payroll system answer');
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

it('shows technician filter options without exposing workload counts', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create([
        'first_name' => 'Workload',
        'last_name' => 'Technician',
        'division' => Division::EDP,
    ]);

    $component = Livewire::actingAs($administrator)
        ->test(ManageItSupportRequests::class)
        ->call('toggleFilters')
        ->assertSee('Workload Technician')
        ->assertDontSee('open)</option>')
        ->assertDontSee('Priority');
    expect($technician->exists)->toBeTrue();
});

it('keeps active queue filters in the URL and pagination links', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $type = RequestType::factory()->create();

    foreach (range(1, 21) as $number) {
        makeDashboardRequest($requester, [
            'request_types_id' => $type->getKey(),
            'status' => ItSupportRequestStatus::Submitted,
        ]);
    }

    $component = Livewire::actingAs($administrator)
        ->withQueryParams([
            'tab' => ItSupportRequestStatus::Submitted->value,
            'request_type' => (string) $type->getKey(),
        ])
        ->test(ManageItSupportRequests::class)
        ->assertSee('wire:click="gotoPage(2', false)
        ->assertSet('status', ItSupportRequestStatus::Submitted->value)
        ->assertSet('requestType', (string) $type->getKey());

    $component->call('gotoPage', 2)
        ->assertSet('status', ItSupportRequestStatus::Submitted->value)
        ->assertSet('requestType', (string) $type->getKey())
        ->assertSee('data-status-tab="submitted"', false)
        ->assertSee(SupportRequest::query()->orderBy('requests_id')->skip(20)->value('ticket_no'));
});

it('orders the intake queue oldest first and removes priority filters and sorting', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $newest = makeDashboardRequest($requester, ['requested_at' => '2026-10-10 09:00:00']);
    $oldest = makeDashboardRequest($requester, ['requested_at' => '2026-10-01 09:00:00']);

    Livewire::actingAs($administrator)
        ->test(ManageItSupportRequests::class)
        ->assertSeeInOrder([$oldest->ticket_no, $newest->ticket_no])
        ->assertDontSee('Priority')
        ->assertDontSee('sort');
});
