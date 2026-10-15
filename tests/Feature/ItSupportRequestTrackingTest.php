<?php

use App\Enums\Role;
use App\Livewire\MyItSupportRequests;
use App\Models\ItSupportRequest;
use App\Models\ItSupportRequestAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows an empty state when the employee has not submitted a request', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('it-support-requests.index'))
        ->assertOk()
        ->assertSee('You have not submitted any IT support requests yet.')
        ->assertSee('Submit your first request');
});

it('lists only the signed-in employee requests in the Livewire table', function () {
    $requester = User::factory()->create();
    $otherUser = User::factory()->create();
    $ownRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'summary' => 'Printer needs repair',
        'support_type' => 'Check / Repair Computer',
        'details' => 'My own support request.',
    ]);
    $otherRequest = ItSupportRequest::factory()->for($otherUser, 'requester')->create([
        'details' => 'Another employee request.',
    ]);

    $this->actingAs($requester);

    Livewire::test(MyItSupportRequests::class)
        ->assertSee($ownRequest->ticket_no)
        ->assertSee(route('it-support-requests.show', $ownRequest), false)
        ->assertSee('data-request-modal-url="'.route('it-support-requests.show', $ownRequest).'"', false)
        ->assertSee('id="request-detail-dialog"', false)
        ->assertSee('items-center justify-center bg-gray-950/60', false)
        ->assertSee('h-[85vh] max-h-[85vh] w-[92vw] max-w-[640px]', false)
        ->assertSee('opacity-0 transition-opacity duration-150', false)
        ->assertSee('id="request-modal-summary"', false)
        ->assertSee('Cancel this request?')
        ->assertSee('This stops work on the request.')
        ->assertSee('undo it.')
        ->assertSee('request-modal-cancel-open', false)
        ->assertSee('request-modal-cancel-keep', false)
        ->assertSee('max-width:80vw;max-height:70vh;width:auto;height:auto;object-fit:contain', false)
        ->assertSee('View original')
        ->assertSee('Printer needs repair')
        ->assertSee('Check / Repair Computer')
        ->assertDontSee('Another employee request.');
});

it('shows a request to its requester', function () {
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'summary' => 'Older ticket summary stays unchanged',
        'support_type' => 'Other IT Request',
        'details' => 'Please help with a system issue.',
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Please help with a system issue.')
        ->assertSee('Older ticket summary stays unchanged')
        ->assertSee('Please Specify')
        ->assertSee('id="request-modal-content"', false)
        ->assertSee('id="request-detail-metadata"', false)
        ->assertSee('data-summary="'.e($supportRequest->summary ?? $supportRequest->support_type).'"', false)
        ->assertSee('EDP-', false)
        ->assertSee('data-request-cancel-form', false)
        ->assertSee('maxlength="300"', false)
        ->assertSee('No attachments uploaded.')
        ->assertDontSee('Summary</dt>')
        ->assertSee('Submitted');
});

it('prevents an employee from viewing or downloading another employee request', function () {
    Storage::fake('private');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $path = 'it-support-requests/private-document.pdf';
    Storage::disk('private')->put($path, '%PDF-1.4 private file');
    $supportRequest = ItSupportRequest::factory()->for($owner, 'requester')->create([
        'attachment_path' => $path,
    ]);

    $this->actingAs($otherUser)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertForbidden();

    $this->get(route('it-support-requests.attachment', $supportRequest))
        ->assertForbidden();
});

it('allows an administrator to view any request', function () {
    $administrator = User::factory()->create(['role' => Role::Administrator]);
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();

    $this->actingAs($administrator)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee($supportRequest->requester_name);
});

it('serves a legacy requester attachment inline with safe headers', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $path = 'it-support-requests/supporting-document.pdf';
    Storage::disk('private')->put($path, '%PDF-1.4 attachment');
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'attachment_path' => $path,
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.attachment', $supportRequest))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Content-Disposition', 'inline; filename=supporting-document.pdf');
});

it('lists every private image and PDF attachment on the request detail page', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create();
    $imagePath = 'it-support-requests/equipment.png';
    $pdfPath = 'it-support-requests/invoice.pdf';
    Storage::disk('private')->put($imagePath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/qN8AAAAASUVORK5CYII='));
    Storage::disk('private')->put($pdfPath, "%PDF-1.4\nexample");

    ItSupportRequestAttachment::create([
        'it_support_request_id' => $supportRequest->getKey(),
        'uploaded_by' => $requester->getKey(),
        'file_path' => $imagePath,
        'original_name' => 'equipment.png',
        'mime_type' => 'image/png',
        'size' => Storage::disk('private')->size($imagePath),
    ]);
    ItSupportRequestAttachment::create([
        'it_support_request_id' => $supportRequest->getKey(),
        'uploaded_by' => $requester->getKey(),
        'file_path' => $pdfPath,
        'original_name' => 'invoice.pdf',
        'mime_type' => 'application/pdf',
        'size' => Storage::disk('private')->size($pdfPath),
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Attachments')
        ->assertSee('Attachments (2)')
        ->assertSee('equipment.png')
        ->assertSee('invoice.pdf')
        ->assertSee('loading="lazy"', false)
        ->assertSee('h-24 w-24', false)
        ->assertSee('display=thumbnail', false)
        ->assertSee('display=preview', false)
        ->assertSee('target="_blank"', false);

    $imageAttachment = $supportRequest->attachments()->where('original_name', 'equipment.png')->firstOrFail();
    $this->get(route('it-support-requests.attachments.download', [$supportRequest, $imageAttachment]).'?display=thumbnail')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename=equipment.png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('uses the authenticated attachment route for legacy attachment paths', function () {
    Storage::fake('private');
    $requester = User::factory()->create();
    $path = 'it-support-requests/legacy-photo.png';
    Storage::disk('private')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/qN8AAAAASUVORK5CYII='));
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'attachment_path' => $path,
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('legacy-photo.png')
        ->assertSee('Attachments (1)')
        ->assertSee(route('it-support-requests.attachment', $supportRequest), false);

    $this->get(route('it-support-requests.attachment', $supportRequest).'?display=preview')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename=legacy-photo.png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('forbids unauthorized users from loading attachment thumbnails and previews', function () {
    Storage::fake('private');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $path = 'it-support-requests/private-image.png';
    Storage::disk('private')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/qN8AAAAASUVORK5CYII='));
    $supportRequest = ItSupportRequest::factory()->for($owner, 'requester')->create();
    $attachment = ItSupportRequestAttachment::create([
        'it_support_request_id' => $supportRequest->getKey(),
        'uploaded_by' => $owner->getKey(),
        'file_path' => $path,
        'original_name' => 'private-image.png',
        'mime_type' => 'image/png',
        'size' => Storage::disk('private')->size($path),
    ]);

    $this->actingAs($otherUser)
        ->get(route('it-support-requests.attachments.download', [$supportRequest, $attachment]).'?display=thumbnail')
        ->assertForbidden();

    $this->get(route('it-support-requests.attachments.download', [$supportRequest, $attachment]).'?display=preview')
        ->assertForbidden();
});

it('requires authentication to view request history', function () {
    $this->get(route('it-support-requests.index'))
        ->assertRedirect(route('login'));
});
