<?php

use App\Enums\Division;
use App\Enums\Role;
use App\Enums\ItSupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\RequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('enforces the technician work lifecycle and stores resolution notes', function () {
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = SupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->user_id,
            'status' => ItSupportRequestStatus::Assigned,
        ]);

    $this->actingAs($requester)
        ->patch(route('it-support-requests.start', $supportRequest))
        ->assertForbidden();

    $this->actingAs($technician)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Start work');

    $this->patch(route('it-support-requests.start', $supportRequest))
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::InProgress)
        ->and($supportRequest->fresh()->started_at)->not->toBeNull()
        ->and($supportRequest->logs()->where('action', 'work_started')->exists())->toBeTrue();

    $this->patch(route('it-support-requests.resolve', $supportRequest), [
        'resolution_notes' => 'Replaced the faulty power supply and verified startup.',
    ])->assertRedirect(route('it-support-requests.show', $supportRequest));

    $supportRequest->refresh();
    expect($supportRequest->status)->toBe(ItSupportRequestStatus::Resolved)
        ->and($supportRequest->resolved_by_user_id)->toBe($technician->user_id)
        ->and($supportRequest->resolved_at)->not->toBeNull()
        ->and($supportRequest->resolution_notes)->toBe('Replaced the faulty power supply and verified startup.')
        ->and($supportRequest->logs()->where('action', 'resolved')->value('from_status'))
        ->toBe(ItSupportRequestStatus::InProgress->value);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Confirm completion')
        ->assertSee('Replaced the faulty power supply and verified startup.');

    $this->actingAs($requester)
        ->patch(route('it-support-requests.complete', $supportRequest))
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Closed)
        ->and($supportRequest->fresh()->closed_at)->not->toBeNull()
        ->and($supportRequest->fresh()->requester_confirmed_at)->not->toBeNull()
        ->and($supportRequest->logs()->where('action', 'requester_confirmed')->value('to_status'))
        ->toBe(ItSupportRequestStatus::Closed->value);
});

it('logs the reason for putting a request on hold and for resuming it', function () {
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = SupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->getKey(),
            'status' => ItSupportRequestStatus::InProgress,
        ]);

    $this->actingAs($technician)
        ->patch(route('it-support-requests.hold', $supportRequest), ['reason' => 'Waiting for replacement parts.'])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::OnHold)
        ->and($supportRequest->logs()->where('action', 'put_on_hold')->value('remarks'))
        ->toBe('Waiting for replacement parts.');

    $this->patch(route('it-support-requests.resume', $supportRequest), ['reason' => 'Replacement parts arrived.'])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::InProgress)
        ->and($supportRequest->logs()->where('action', 'resumed')->value('remarks'))
        ->toBe('Replacement parts arrived.');
});

it('stores technician proof photos as request attachments with the uploader', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = SupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->getKey(),
            'status' => ItSupportRequestStatus::InProgress,
        ]);

    $this->actingAs($technician)
        ->post(route('it-support-requests.attachments.store-proof', $supportRequest), [
            'proof' => UploadedFile::fake()->create('network-test.png', 10, 'image/png'),
        ])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    $attachment = RequestAttachment::query()->firstOrFail();
    expect($attachment->requests_id)->toBe($supportRequest->getKey())
        ->and($attachment->uploaded_by)->toBe($technician->getKey())
        ->and($attachment->original_name)->toBe('network-test.png')
        ->and($supportRequest->logs()->where('action', 'attachment_uploaded')->exists())->toBeTrue();

    Storage::disk('private')->assertExists($attachment->file_path);
});

it('denies proof uploads by requesters and for attachments belonging to another request', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $firstRequest = SupportRequest::factory()->for($requester, 'requester')->create([
        'assigned_technician_id' => $technician->getKey(),
        'status' => ItSupportRequestStatus::InProgress,
    ]);
    $secondRequest = SupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($requester)
        ->post(route('it-support-requests.attachments.store-proof', $firstRequest), [
            'proof' => UploadedFile::fake()->create('not-allowed.png', 10, 'image/png'),
        ])
        ->assertForbidden();

    $attachment = RequestAttachment::factory()->create([
        'requests_id' => $secondRequest->getKey(),
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.attachments.download', [$firstRequest, $attachment]))
        ->assertNotFound();

    Storage::disk('private')->assertDirectoryEmpty('it-support-requests/proofs');
});

it('allows technicians to access their assigned queue without changing work permissions', function () {
    $technician = User::factory()->create(['division' => Division::EDP]);
    $this->actingAs($technician)->get(route('edp.assigned-requests.index'))->assertOk();

    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $this->actingAs($administrator)->get(route('edp.assigned-requests.index'))->assertOk();

    $inactiveTechnician = User::factory()->create([
        'division' => Division::EDP,
        'is_active' => false,
    ]);
    $supportRequest = SupportRequest::factory()->create([
        'assigned_technician_id' => $inactiveTechnician->getKey(),
        'status' => ItSupportRequestStatus::Assigned,
    ]);

    $this->actingAs($inactiveTechnician)
        ->patch(route('it-support-requests.start', $supportRequest))
        ->assertForbidden();
});
