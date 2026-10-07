<?php

use App\Enums\Role;
use App\Livewire\MyItSupportRequests;
use App\Models\ItSupportRequest;
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
        'details' => 'My own support request.',
    ]);
    $otherRequest = ItSupportRequest::factory()->for($otherUser, 'requester')->create([
        'details' => 'Another employee request.',
    ]);

    $this->actingAs($requester);

    Livewire::test(MyItSupportRequests::class)
        ->assertSee('#'.$ownRequest->getKey())
        ->assertSee('My own support request.')
        ->assertDontSee('Another employee request.');
});

it('shows a request to its requester', function () {
    $requester = User::factory()->create();
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'support_type' => 'Other IT Request',
        'details' => 'Please help with a system issue.',
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Please help with a system issue.')
        ->assertSee('Please Specify')
        ->assertSee('Submitted');
});

it('prevents an employee from viewing or downloading another employee request', function () {
    Storage::fake('local');
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $path = 'it-support-requests/private-document.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 private file');
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

it('allows the requester to download their private attachment', function () {
    Storage::fake('local');
    $requester = User::factory()->create();
    $path = 'it-support-requests/supporting-document.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 attachment');
    $supportRequest = ItSupportRequest::factory()->for($requester, 'requester')->create([
        'attachment_path' => $path,
    ]);

    $this->actingAs($requester)
        ->get(route('it-support-requests.attachment', $supportRequest))
        ->assertOk()
        ->assertDownload("it-support-request-{$supportRequest->getKey()}.pdf");
});

it('requires authentication to view request history', function () {
    $this->get(route('it-support-requests.index'))
        ->assertRedirect(route('login'));
});
