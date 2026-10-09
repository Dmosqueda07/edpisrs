<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestStatus;
use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('enforces the technician work lifecycle and requester completion confirmation', function () {
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()
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

    $this->patch(route('it-support-requests.resolve', $supportRequest))
        ->assertForbidden();

    $this->patch(route('it-support-requests.start', $supportRequest))
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::InProgress)
        ->and($supportRequest->fresh()->started_at)->not->toBeNull()
        ->and($supportRequest->logs()->where('action', 'work_started')->exists())->toBeTrue();

    $this->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Sign off and mark resolved');

    $this->patch(route('it-support-requests.resolve', $supportRequest))
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    $supportRequest->refresh();
    expect($supportRequest->status)->toBe(ItSupportRequestStatus::Resolved)
        ->and($supportRequest->resolved_by_user_id)->toBe($technician->user_id)
        ->and($supportRequest->resolved_at)->not->toBeNull()
        ->and($supportRequest->logs()->where('action', 'resolved')->value('from_status'))
        ->toBe(ItSupportRequestStatus::InProgress->value);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Confirm completion');

    $this->actingAs($technician)
        ->patch(route('it-support-requests.complete', $supportRequest))
        ->assertForbidden();

    $this->actingAs($requester)
        ->patch(route('it-support-requests.complete', $supportRequest))
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->fresh()->status)->toBe(ItSupportRequestStatus::Closed)
        ->and($supportRequest->fresh()->closed_at)->not->toBeNull()
        ->and($supportRequest->fresh()->requester_confirmed_at)->not->toBeNull()
        ->and($supportRequest->logs()->where('action', 'requester_confirmed')->value('to_status'))
        ->toBe(ItSupportRequestStatus::Closed->value);
});

it('only allows active EDP technicians to access their assigned work queue', function () {
    $technician = User::factory()->create(['division' => Division::EDP]);
    $otherTechnician = User::factory()->create(['division' => Division::EDP]);
    $requester = User::factory()->create();
    $assignedToMe = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->user_id,
            'details' => 'Assigned to this technician.',
        ]);
    $assignedElsewhere = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $otherTechnician->user_id,
            'details' => 'Assigned to another technician.',
        ]);

    $this->actingAs($technician)
        ->get(route('edp.assigned-requests.index'))
        ->assertOk()
        ->assertSee('#'.$assignedToMe->getKey())
        ->assertSee('Assigned to this technician.')
        ->assertDontSee('Assigned to another technician.');

    $inactiveTechnician = User::factory()->create([
        'division' => Division::EDP,
        'is_active' => false,
    ]);

    $this->actingAs($inactiveTechnician)
        ->get(route('edp.assigned-requests.index'))
        ->assertForbidden();
});

it('prevents an inactive assigned technician from updating a request', function () {
    $requester = User::factory()->create();
    $technician = User::factory()->create([
        'division' => Division::EDP,
        'is_active' => false,
    ]);
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->user_id,
            'status' => ItSupportRequestStatus::Assigned,
        ]);

    $this->actingAs($technician)
        ->patch(route('it-support-requests.start', $supportRequest))
        ->assertForbidden();
});

it('lets the requester and assigned technician share comments and private proof', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->user_id,
            'status' => ItSupportRequestStatus::InProgress,
        ]);

    $this->actingAs($requester)
        ->post(route('it-support-requests.comments.store', $supportRequest), [
            'body' => 'The issue is still happening.',
        ])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    $this->actingAs($technician)
        ->post(route('it-support-requests.comments.store', $supportRequest), [
            'body' => 'Replaced the network cable and tested the connection.',
            'proof' => UploadedFile::fake()->createWithContent('network-test.pdf', "%PDF-1.4\nproof"),
        ])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    $proofComment = ItSupportRequestComment::query()
        ->where('it_support_request_id', $supportRequest->getKey())
        ->whereNotNull('proof_path')
        ->firstOrFail();

    expect($proofComment->author_name)->toBe($technician->full_name)
        ->and($proofComment->body)->toBe('Replaced the network cable and tested the connection.');

    Storage::disk('private')->assertExists($proofComment->proof_path);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('The issue is still happening.')
        ->assertSee('Replaced the network cable and tested the connection.')
        ->assertSee($technician->full_name);

    $this->get(route('it-support-requests.comment-proof', [$supportRequest, $proofComment]))
        ->assertOk()
        ->assertDownload("it-support-request-{$supportRequest->getKey()}-proof-{$proofComment->getKey()}.pdf");

    expect($supportRequest->logs()->where('action', 'comment_added')->exists())->toBeTrue()
        ->and($supportRequest->logs()->where('action', 'attachment_uploaded')->exists())->toBeTrue();
});

it('logs internal notes and hides them from the requester', function () {
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'assigned_technician_id' => $technician->user_id,
        'status' => ItSupportRequestStatus::InProgress,
    ]);

    $this->actingAs($technician)
        ->post(route('it-support-requests.comments.store', $supportRequest), [
            'body' => 'Check with network team before closing.',
            'is_internal' => '1',
        ])
        ->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->logs()->where('action', 'internal_note_added')->value('user_id'))
        ->toBe($technician->user_id);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertDontSee('Check with network team before closing.');

    $this->actingAs($technician)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Check with network team before closing.')
        ->assertSee('Internal note');
});

it('does not allow requesters to upload technician proof', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $technician = User::factory()->create(['division' => Division::EDP]);
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'assigned_technician_id' => $technician->user_id,
            'status' => ItSupportRequestStatus::InProgress,
        ]);

    $this->actingAs($requester)
        ->post(route('it-support-requests.comments.store', $supportRequest), [
            'body' => 'Here is my proof.',
            'proof' => UploadedFile::fake()->createWithContent('proof.pdf', "%PDF-1.4\nproof"),
        ])
        ->assertForbidden();

    expect($supportRequest->comments()->count())->toBe(0);
    Storage::disk('private')->assertDirectoryEmpty('it-support-requests/proofs');
});

it('does not serve proof attached to a different request', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $firstRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();
    $secondRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();
    $proofPath = 'it-support-requests/proofs/other-request.pdf';
    Storage::disk('private')->put($proofPath, '%PDF-1.4 other request');
    $comment = $secondRequest->comments()->create([
        'user_id' => $requester->user_id,
        'author_name' => $requester->full_name,
        'body' => 'Proof for the second request.',
        'proof_path' => $proofPath,
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.comment-proof', [$firstRequest, $comment]))
        ->assertNotFound();
});

it('prevents comments after requester completion', function () {
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()
        ->for($requester, 'requester')
        ->create([
            'status' => ItSupportRequestStatus::Closed,
            'completed_at' => now(),
        ]);

    $this->actingAs($requester)
        ->post(route('it-support-requests.comments.store', $supportRequest), [
            'body' => 'A late update.',
        ])
        ->assertForbidden();

    expect($supportRequest->comments()->count())->toBe(0);
});
