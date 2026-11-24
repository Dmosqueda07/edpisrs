<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Enums\Role;
use App\Models\SupportRequest;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RequestTypeSeeder::class);
});

it('submits formerly sensitive request types directly to the assignment queue', function () {
    $requester = User::factory()->create(['division' => Division::PVSD]);
    $technician = User::factory()->create(['division' => Division::EDP]);
    $requestType = RequestType::where('key', 'extract-database-records')->firstOrFail();

    $this->actingAs($requester)
        ->post(route('it-support-requests.store'), [
            'summary' => 'Records extraction request',
            'request_type_id' => $requestType->getKey(),
            'answers' => ['details' => 'Extract records for the monthly report.'],
            'certification' => '1',
        ])
        ->assertRedirect();

    $supportRequest = SupportRequest::query()->firstOrFail();

    expect($supportRequest->status)->toBe(ItSupportRequestStatus::Submitted)
        ->and($supportRequest->request_types_id)->toBe($requestType->getKey())
        ->and($supportRequest->answers)->toBe(['details' => 'Extract records for the monthly report.'])
        ->and($supportRequest->assigned_technician_id)->toBeNull()
        ->and($supportRequest->logs()->where('action', 'submitted')->exists())->toBeTrue();

    $this->actingAs($technician)
        ->patch(route('edp.it-support-requests.claim', $supportRequest))
        ->assertRedirect(route('edp.it-support-requests.index'));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Assigned)
        ->and($supportRequest->fresh()->assigned_technician_id)->toBe($technician->getKey());
});

it('rejects answers not declared for the selected request type', function () {
    $requester = User::factory()->create();
    $requestType = RequestType::where('key', 'check-repair-computer')->firstOrFail();

    $this->actingAs($requester)
        ->from(route('it-support-requests.create'))
        ->post(route('it-support-requests.store'), [
            'summary' => 'Unexpected answer key',
            'request_type_id' => $requestType->getKey(),
            'answers' => [
                'details' => 'Computer issue.',
                'unconfigured_field' => 'Must not be accepted.',
            ],
            'certification' => '1',
        ])
        ->assertRedirect(route('it-support-requests.create'))
        ->assertSessionHasErrors('answers');

    expect(SupportRequest::query()->count())->toBe(0);
});

it('removes request approval and discussion routes and keeps approver roles intact', function () {
    expect(Route::has('approvals.it-support-requests.index'))->toBeFalse()
        ->and(Route::has('it-support-requests.approval'))->toBeFalse()
        ->and(Route::has('it-support-requests.comments.store'))->toBeFalse()
        ->and(Role::cases())->toContain(Role::DivisionHead, Role::RecordsChief, Role::CityAssessor);
});
