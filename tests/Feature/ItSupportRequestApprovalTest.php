<?php

use App\Enums\ApprovalDecision;
use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Livewire\ItSupportRequestApprovalQueue;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RequestTypeSeeder::class);
});

it('seeds all configured request types and their approval requirements', function () {
    $recordExtraction = RequestType::where('key', 'extract-database-records')->firstOrFail();
    $fileAccessTypes = RequestType::whereIn('key', [
        'grant-restore-file-access',
        'file-folder-access-request',
    ])->get();

    expect(RequestType::count())->toBe(12)
        ->and(RequestType::where('requires_approval', true)->pluck('key')->all())->toBe([
            'extract-database-records',
            'grant-restore-file-access',
            'file-folder-access-request',
        ])
        ->and($recordExtraction->approval_roles)->toContain(
            Role::SuperAdministrator->value,
            Role::Administrator->value,
            Role::RecordsChief->value,
        );

    foreach ($fileAccessTypes as $requestType) {
        expect($requestType->approval_roles)->toContain(
            Role::SuperAdministrator->value,
            Role::Administrator->value,
            Role::DivisionHead->value,
            Role::CityAssessor->value,
        )->not->toContain(Role::RecordsChief->value);
    }
});

it('updates request type behavior from configuration when reseeded', function () {
    $types = config('request_forms.request_types');
    $types[0]['requires_approval'] = true;
    $types[0]['approval_roles'] = [Role::Administrator->value];
    Config::set('request_forms.request_types', $types);

    $this->seed(RequestTypeSeeder::class);

    $type = RequestType::where('key', 'check-repair-computer')->firstOrFail();

    expect($type->requires_approval)->toBeTrue()
        ->and($type->approval_roles)->toBe([Role::Administrator->value]);
});

it('submits sensitive request types as pending approval with a rule snapshot', function () {
    $requester = User::factory()->create(['division' => Division::PVSD]);
    $requestType = RequestType::where('key', 'extract-database-records')->firstOrFail();

    $this->actingAs($requester)
        ->post(route('it-support-requests.store'), [
            'request_type_id' => $requestType->getKey(),
            'details' => 'Extract the requested records for review.',
            'certification' => '1',
        ])
        ->assertRedirect();

    $supportRequest = ItSupportRequest::query()->firstOrFail();

    expect($supportRequest->status)->toBe(ItSupportRequestStatus::PendingApproval)
        ->and($supportRequest->request_type_id)->toBe($requestType->getKey())
        ->and($supportRequest->support_type)->toBe($requestType->name)
        ->and($supportRequest->follow_up_question)->toBe($requestType->follow_up_question)
        ->and($supportRequest->requires_approval)->toBeTrue()
        ->and($supportRequest->approval_roles)->toBe($requestType->approval_roles);
});

it('shows only pending requests eligible for the signed-in approver', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $eligibleRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'status' => ItSupportRequestStatus::PendingApproval,
            'requires_approval' => true,
            'approval_roles' => [Role::Administrator->value],
        ]);
    ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'status' => ItSupportRequestStatus::PendingApproval,
            'requires_approval' => true,
            'approval_roles' => [Role::RecordsChief->value],
        ]);

    $this->actingAs($administrator)
        ->get(route('approvals.it-support-requests.index'))
        ->assertOk();

    Livewire::actingAs($administrator)
        ->test(ItSupportRequestApprovalQueue::class)
        ->assertSee('#'.$eligibleRequest->getKey())
        ->assertSee($eligibleRequest->requester_name);
});

it('approves a pending request once and makes it available for assignment', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'status' => ItSupportRequestStatus::PendingApproval,
            'requires_approval' => true,
            'approval_roles' => [Role::Administrator->value],
        ]);

    $this->actingAs($administrator)
        ->patch(route('edp.it-support-requests.assign', $supportRequest), [
            'technician_id' => $technician->user_id,
        ])->assertForbidden();

    $this->actingAs($administrator)
        ->patch(route('it-support-requests.approval', $supportRequest), [
            'decision' => ApprovalDecision::Approved->value,
            'comment' => 'Approved for processing.',
        ])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    $supportRequest->refresh();
    $approval = $supportRequest->approvals()->firstOrFail();

    expect($supportRequest->status)->toBe(ItSupportRequestStatus::Submitted)
        ->and($approval->decision)->toBe(ApprovalDecision::Approved)
        ->and($approval->approver_user_id)->toBe($administrator->user_id)
        ->and($approval->comment)->toBe('Approved for processing.');

    $this->patch(route('edp.it-support-requests.assign', $supportRequest), [
        'technician_id' => $technician->user_id,
    ])->assertRedirect(route('edp.it-support-requests.index'));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Assigned);

    $this->patch(route('it-support-requests.approval', $supportRequest), [
        'decision' => ApprovalDecision::Rejected->value,
    ])->assertForbidden();

    expect($supportRequest->approvals()->count())->toBe(1);
});

it('rejects a request and prevents it from being assigned', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $technician = User::factory()->create(['division' => Division::EDP]);
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'status' => ItSupportRequestStatus::PendingApproval,
            'requires_approval' => true,
            'approval_roles' => [Role::Administrator->value],
        ]);

    $this->actingAs($administrator)
        ->patch(route('it-support-requests.approval', $supportRequest), [
            'decision' => ApprovalDecision::Rejected->value,
        ])
        ->assertRedirect();

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Rejected)
        ->and($supportRequest->approvals()->firstOrFail()->decision)->toBe(ApprovalDecision::Rejected);

    $this->patch(route('edp.it-support-requests.assign', $supportRequest), [
        'technician_id' => $technician->user_id,
    ])->assertForbidden();
});

it('limits division head approval to requests from the same division', function () {
    $head = User::factory()->create([
        'role' => Role::DivisionHead,
        'division' => Division::PVSD,
    ]);
    $sameDivisionRequest = ItSupportRequest::factory()->create([
        'division' => Division::PVSD->value,
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
        'approval_roles' => [Role::DivisionHead->value],
    ]);
    $otherDivisionRequest = ItSupportRequest::factory()->create([
        'division' => Division::CA->value,
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
        'approval_roles' => [Role::DivisionHead->value],
    ]);

    $this->actingAs($head)
        ->patch(route('it-support-requests.approval', $sameDivisionRequest), [
            'decision' => ApprovalDecision::Approved->value,
        ])
        ->assertRedirect();

    $this->patch(route('it-support-requests.approval', $otherDivisionRequest), [
        'decision' => ApprovalDecision::Approved->value,
    ])->assertForbidden();
});

it('limits records chief approval to database record requests', function () {
    $recordsChief = User::factory()->create(['role' => Role::RecordsChief]);
    $extraction = ItSupportRequest::factory()->create([
        'support_type' => 'Extract Database Records (System / Database)',
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
        'approval_roles' => [
            Role::Administrator->value,
            Role::RecordsChief->value,
        ],
    ]);
    $fileAccess = ItSupportRequest::factory()->create([
        'support_type' => 'Grant / Restore File Access',
        'status' => ItSupportRequestStatus::PendingApproval,
        'requires_approval' => true,
        'approval_roles' => [
            Role::Administrator->value,
            Role::DivisionHead->value,
            Role::CityAssessor->value,
        ],
    ]);

    $this->actingAs($recordsChief)
        ->patch(route('it-support-requests.approval', $extraction), [
            'decision' => ApprovalDecision::Approved->value,
        ])
        ->assertRedirect();

    $this->patch(route('it-support-requests.approval', $fileAccess), [
        'decision' => ApprovalDecision::Approved->value,
    ])->assertForbidden();
});

it('lets city assessor approve any sensitive request and bypasses approval for other types', function () {
    $cityAssessor = User::factory()->create(['role' => Role::CityAssessor]);
    $requester = User::factory()->create();
    $sensitiveRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'status' => ItSupportRequestStatus::PendingApproval,
            'requires_approval' => true,
            'approval_roles' => [Role::CityAssessor->value],
        ]);

    $this->actingAs($cityAssessor)
        ->patch(route('it-support-requests.approval', $sensitiveRequest), [
            'decision' => ApprovalDecision::Approved->value,
        ])
        ->assertRedirect();

    $normalType = RequestType::where('key', 'other-it-request')->firstOrFail();
    $this->actingAs($requester)
        ->post(route('it-support-requests.store'), [
            'request_type_id' => $normalType->getKey(),
            'details' => 'Please provide standard assistance.',
            'certification' => '1',
        ])
        ->assertRedirect();

    expect(ItSupportRequest::latest('id')->firstOrFail()->status)
        ->toBe(ItSupportRequestStatus::Submitted);
});
