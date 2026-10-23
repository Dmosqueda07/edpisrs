<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Models\ItSupportRequest;
use App\Models\User;
use App\Services\RequestDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

function navigationFor(User $user): string
{
    return Volt::actingAs($user)->test('layout.navigation')->html();
}

it('shows links according to existing role policies', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $navigation = navigationFor($administrator);
    expect($navigation)->toContain('Dashboard', 'EDP intake', 'Assigned to me', 'Reports', 'Admin', 'User management', 'Activity logs')
        ->and($navigation)->not->toContain('Approvals');

    $superAdministrator = User::factory()->create(['role' => Role::SuperAdministrator]);
    $navigation = navigationFor($superAdministrator);
    expect($navigation)->toContain('EDP intake', 'Reports', 'Admin');

    $approver = User::factory()->create(['role' => Role::DivisionHead, 'division' => Division::CA]);
    $navigation = navigationFor($approver);
    expect($navigation)->toContain('Dashboard', 'Submit a request', 'My requests', 'Approvals')
        ->and($navigation)->not->toContain('EDP intake', 'Reports', 'User management');

    $technician = User::factory()->create(['role' => Role::Staff, 'division' => Division::EDP]);
    $navigation = navigationFor($technician);
    expect($navigation)->toContain('Dashboard', 'Assigned to me', 'Submit a request', 'My requests')
        ->and($navigation)->not->toContain('EDP intake', 'Approvals', 'Admin');
    $this->actingAs($technician)->get(route('edp.assigned-requests.index'))->assertOk();

    $employee = User::factory()->create(['role' => Role::Staff, 'division' => Division::CA]);
    $navigation = navigationFor($employee);
    expect($navigation)->toContain('Dashboard', 'Submit a request', 'My requests')
        ->and($navigation)->not->toContain('EDP intake', 'Assigned to me', 'Approvals', 'Admin');
});

it('redirects administrators from the old approvals URL to pending approval in EDP intake', function () {
    foreach ([Role::Administrator, Role::SuperAdministrator] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)
            ->get(route('approvals.it-support-requests.index'))
            ->assertRedirect(route('edp.it-support-requests.index', ['tab' => 'pending_approval']));
    }

    $approver = User::factory()->create(['role' => Role::DivisionHead]);
    $this->actingAs($approver)->get(route('approvals.it-support-requests.index'))->assertOk();
});

it('shows grouped badge totals and hides badges at zero', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    ItSupportRequest::factory()->create([
        'requester_user_id' => $requester->getKey(),
        'status' => ItSupportRequestStatus::Submitted,
        'requires_approval' => false,
        'assigned_technician_id' => null,
    ]);
    ItSupportRequest::factory()->create([
        'requester_user_id' => $requester->getKey(),
        'status' => ItSupportRequestStatus::Approved,
        'assigned_technician_id' => null,
    ]);
    ItSupportRequest::factory()->create([
        'requester_user_id' => $requester->getKey(),
        'assigned_technician_id' => $administrator->getKey(),
        'status' => ItSupportRequestStatus::InProgress,
    ]);
    $badgeQueries = [];
    DB::listen(function ($query) use (&$badgeQueries): void {
        if (str_contains(strtolower($query->sql), 'union all') && str_contains(strtolower($query->sql), "'intake' as badge")) {
            $badgeQueries[] = $query->sql;
        }
    });

    $navigation = Volt::actingAs($administrator)->test('layout.navigation')
        ->assertSee('>2</span>', false)
        ->assertSee('>1</span>', false);
    expect(substr_count($navigation->html(), 'class="app-nav-badge"'))->toBe(4);
    expect($badgeQueries)->toHaveCount(1);

    ItSupportRequest::query()->delete();
    $emptyAdmin = User::factory()->create(['role' => Role::Administrator]);
    Volt::actingAs($emptyAdmin)->test('layout.navigation')->assertDontSee('app-nav-badge', false);

    $approver = User::factory()->create(['role' => Role::DivisionHead, 'division' => Division::CA]);
    ItSupportRequest::factory()->create([
        'requester_user_id' => $requester->getKey(),
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
        'division' => Division::CA,
        'approval_roles' => [Role::DivisionHead->value],
    ]);
    Volt::actingAs($approver)->test('layout.navigation')
        ->assertSee('app-nav-badge-attention', false)
        ->assertSee('>1</span>', false);
});

it('keeps account links inside a closed user menu on desktop and mobile', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $html = Volt::actingAs($administrator)->test('layout.navigation')->html();
    preg_match_all('/<div id="(?:desktop|mobile)-user-menu"[^>]*role="menu">(.*?)<\/div>/s', $html, $matches);
    $menus = $matches[1] ?? [];

    expect(substr_count($html, 'x-show="userOpen"'))->toBe(2)
        ->and(substr_count($html, 'aria-haspopup="menu"'))->toBeGreaterThanOrEqual(3)
        ->and($html)->toContain('x-cloak x-show="userOpen"')
        ->and($html)->toContain('wire:click="logout"')
        ->and($html)->not->toContain('Find ticket', 'ticketSearch', 'findTicket', 'Ticket not found')
        ->and($menus)->toHaveCount(2);

    foreach (['My requests', 'Submit a request', 'Profile', 'Log out'] as $label) {
        foreach ($menus as $menu) {
            expect($menu)->toContain($label);
        }
    }
});

it('uses the shared open assigned statuses for the navbar badge and dashboard line', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();

    foreach ([
        ItSupportRequestStatus::Assigned,
        ItSupportRequestStatus::InProgress,
        ItSupportRequestStatus::OnHold,
        ItSupportRequestStatus::Resolved,
        ItSupportRequestStatus::Closed,
    ] as $status) {
        ItSupportRequest::factory()->create([
            'requester_user_id' => $requester->getKey(),
            'assigned_technician_id' => $administrator->getKey(),
            'status' => $status,
        ]);
    }

    expect(app(RequestDashboardMetrics::class)->administratorDashboard($administrator)['assignedCount'])->toBe(3);

    $this->actingAs($administrator)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('You have 3 assigned requests');

    $html = Volt::actingAs($administrator)->test('layout.navigation')->html();
    expect(substr_count($html, 'app-nav-badge">3</span>'))->toBe(2);
});
