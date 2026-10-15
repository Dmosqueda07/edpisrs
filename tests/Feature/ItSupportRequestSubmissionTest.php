<?php

use App\Enums\Division;
use App\Enums\ItSupportRequestPriority;
use App\Livewire\ItSupportRequestForm;
use App\Mail\ItSupportRequestReceived;
use App\Models\ItSupportRequest;
use App\Models\RequestType;
use App\Models\User;
use Database\Seeders\RequestTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RequestTypeSeeder::class);
});

it('displays the request form with the signed-in user information and all support types', function () {
    $user = User::factory()->create([
        'first_name' => 'Avery',
        'last_name' => 'Employee',
        'division' => Division::EDP,
    ]);

    $this->actingAs($user)
        ->get(route('it-support-requests.create'))
        ->assertOk()
        ->assertSee('Avery Employee')
        ->assertSee('Short title')
        ->assertSee('Example: Printer on the 2nd floor')
        ->assertSee('won')
        ->assertSee('print')
        ->assertSee(Division::EDP->value)
        ->assertSee('For urgent concerns requiring immediate assistance, coordinate directly')
        ->assertSee('EDP IT Support Team.')
        ->assertSee('Not urgent, can wait')
        ->assertSee('Default, regular work')
        ->assertSee('Affects my work')
        ->assertSee('Work is stopped')
        ->assertSee('Check / Repair Computer')
        ->assertSee('Other IT Request');
});

it('shows the matching required follow-up prompt for every support type', function () {
    $this->actingAs(User::factory()->create());

    $requestTypes = RequestType::active()->get();

    expect($requestTypes->pluck('follow_up_question')->all())->toBe([
        'Details/Action Needed',
        'System/Application',
        'Location/Details',
        'Account/Action Needed',
        'Issue/Action Needed',
        'Records/Data',
        'File/Folder/System',
        'Email/Account',
        'Equipment/Details',
        'Software/Application',
        'Issue/Error',
        'Please Specify',
    ]);

    foreach ($requestTypes as $type) {
        Livewire::test(ItSupportRequestForm::class)
            ->set('requestTypeId', (string) $type->getKey())
            ->assertSee($type->follow_up_question);
    }
});

it('stores a certified request and private attachment and queues confirmation email', function () {
    Storage::fake('private');
    Mail::fake();

    $user = User::factory()->create([
        'first_name' => 'Avery',
        'last_name' => 'Employee',
        'division' => Division::EDP,
    ]);
    $requestType = RequestType::where('key', 'check-repair-computer')->firstOrFail();

    $response = $this->actingAs($user)->post(route('it-support-requests.store'), [
        'summary' => 'Computer will not start',
        'request_type_id' => $requestType->getKey(),
        'answers' => ['details' => 'Computer does not start after a restart.'],
        'attachments' => [
            UploadedFile::fake()->createWithContent('computer.pdf', "%PDF-1.4\nexample"),
        ],
        'certification' => '1',
    ]);

    $supportRequest = ItSupportRequest::query()->firstOrFail();
    $response->assertRedirect(route('it-support-requests.show', $supportRequest));

    expect($supportRequest->requester_user_id)->toBe($user->user_id)
        ->and($supportRequest->requester_name)->toBe('Avery Employee')
        ->and($supportRequest->division)->toBe(Division::EDP->value)
        ->and($supportRequest->summary)->toBe('Computer will not start')
        ->and($supportRequest->request_type_id)->toBe($requestType->getKey())
        ->and($supportRequest->support_type)->toBe($requestType->name)
        ->and($supportRequest->priority)->toBe(ItSupportRequestPriority::Normal)
        ->and($supportRequest->summary)->toBe('Computer will not start')
        ->and($supportRequest->detailsRows()->firstOrFail()->field_label)->toBe($requestType->follow_up_question)
        ->and($supportRequest->detailsRows()->firstOrFail()->field_value)->toBe('Computer does not start after a restart.')
        ->and($supportRequest->certified_at)->not->toBeNull()
        ->and($supportRequest->status->value)->toBe('submitted');

    $attachment = $supportRequest->attachments()->firstOrFail();
    Storage::disk('private')->assertExists($attachment->file_path);
    expect($attachment->uploaded_by)->toBe($user->user_id)
        ->and($supportRequest->logs()->where('action', 'submitted')->value('remarks'))->toBe('Priority: Normal')
        ->and($supportRequest->logs()->where('action', 'attachment_uploaded')->exists())->toBeTrue();

    $this->actingAs($user)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk()
        ->assertSee('Computer does not start after a restart.')
        ->assertSee('Computer will not start')
        ->assertSee('Normal')
        ->assertDontSee('Type of IT Support Needed')
        ->assertDontSee('Certification</dt>')
        ->assertSee('· Certified')
        ->assertSee('computer.pdf');

    Livewire::actingAs($user)
        ->test(\App\Livewire\MyItSupportRequests::class)
        ->assertSee('Normal');

    $this->get(route('it-support-requests.attachments.download', [$supportRequest, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=computer.pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $otherUser = User::factory()->create();
    $this->actingAs($otherUser)
        ->get(route('it-support-requests.attachments.download', [$supportRequest, $attachment]))
        ->assertForbidden();

    $otherRequest = ItSupportRequest::factory()->for($user, 'requester')->create();
    $this->actingAs($user)
        ->get(route('it-support-requests.attachments.download', [$otherRequest, $attachment]))
        ->assertNotFound();
    Mail::assertQueued(ItSupportRequestReceived::class, fn (ItSupportRequestReceived $mail) =>
        $mail->hasTo($user->email)
        && $mail->envelope()->subject === 'IT Support Request Received: Computer will not start'
    );
});

it('allows the requester to view their request when the database returns the user id as a string', function () {
    $user = User::factory()->create(['role' => \App\Enums\Role::Staff]);
    $supportRequest = ItSupportRequest::factory()->for($user, 'requester')->create();
    $supportRequest->setAttribute('requester_user_id', (string) $user->getKey());

    $this->actingAs($user)
        ->get(route('it-support-requests.show', $supportRequest))
        ->assertOk();
});

it('accepts and records each valid request priority', function () {
    Mail::fake();
    $requestType = RequestType::where('key', 'other-it-request')->firstOrFail();

    foreach (ItSupportRequestPriority::cases() as $priority) {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('it-support-requests.store'), [
            'summary' => 'Help with my request',
            'request_type_id' => $requestType->getKey(),
            'answers' => ['details' => 'Please help with this request.'],
            'priority' => $priority->value,
            'certification' => '1',
        ])->assertRedirect();

        $supportRequest = $user->itSupportRequests()->latest('id')->firstOrFail();
        expect($supportRequest->priority)->toBe($priority)
            ->and($supportRequest->logs()->where('action', 'submitted')->value('remarks'))
            ->toBe('Priority: '.\Illuminate\Support\Str::headline($priority->value));

        $this->get(route('it-support-requests.show', $supportRequest))
            ->assertOk()
            ->assertSee(\Illuminate\Support\Str::headline($priority->value));
    }
});

it('requires a short title of no more than 120 characters', function () {
    Mail::fake();
    $user = User::factory()->create();
    $requestType = RequestType::where('key', 'other-it-request')->firstOrFail();
    $base = [
        'request_type_id' => $requestType->getKey(),
        'answers' => ['details' => 'Please help with this request.'],
        'certification' => '1',
    ];

    $this->actingAs($user)
        ->post(route('it-support-requests.store'), $base)
        ->assertSessionHasErrors('summary');

    $this->post(route('it-support-requests.store'), [
        ...$base,
        'summary' => str_repeat('x', 121),
    ])->assertSessionHasErrors('summary');

    $this->post(route('it-support-requests.store'), [
        ...$base,
        'summary' => str_repeat('x', 120),
    ])->assertRedirect();

    expect(ItSupportRequest::query()->count())->toBe(1)
        ->and(ItSupportRequest::query()->firstOrFail()->summary)->toBe(str_repeat('x', 120));
});

it('rejects a priority value outside the priority enum', function () {
    $user = User::factory()->create();
    $requestType = RequestType::where('key', 'other-it-request')->firstOrFail();

    $this->actingAs($user)->post(route('it-support-requests.store'), [
        'summary' => 'Help with my request',
        'request_type_id' => $requestType->getKey(),
        'answers' => ['details' => 'Please help with this request.'],
        'priority' => 'critical',
        'certification' => '1',
    ])->assertSessionHasErrors('priority');

    expect(ItSupportRequest::query()->count())->toBe(0);
});

it('accepts a request without an optional attachment', function () {
    Mail::fake();
    $user = User::factory()->create();
    $requestType = RequestType::where('key', 'other-it-request')->firstOrFail();

    $this->actingAs($user)
        ->post(route('it-support-requests.store'), [
            'summary' => 'Please specify support',
            'request_type_id' => $requestType->getKey(),
            'answers' => ['details' => 'Please specify the support required.'],
            'certification' => '1',
        ])
        ->assertRedirect(route('it-support-requests.show', ItSupportRequest::query()->firstOrFail()));

    expect(ItSupportRequest::query()->firstOrFail()->attachments()->count())->toBe(0);
});

it('requires a known support type, details, and certification', function () {
    $user = User::factory()->create();
    $requestType = RequestType::where('key', 'other-it-request')->firstOrFail();

    $this->actingAs($user)
        ->post(route('it-support-requests.store'), [
            'request_type_id' => 'not-an-id',
        ])
        ->assertSessionHasErrors(['request_type_id', 'certification']);

    $this->post(route('it-support-requests.store'), [
        'request_type_id' => $requestType->getKey(),
        'certification' => '1',
    ])->assertSessionHasErrors('answers.details');

    expect(ItSupportRequest::query()->count())->toBe(0);
});

it('rejects invalid real MIME types, attachments over 5 MB, and more than five files', function () {
    $user = User::factory()->create();
    $requestType = RequestType::where('key', 'other-it-request')->firstOrFail();
    $requestData = [
        'summary' => 'Install approved application',
        'request_type_id' => $requestType->getKey(),
        'answers' => ['details' => 'Please install an approved application.'],
        'certification' => '1',
    ];

    $this->actingAs($user)
        ->post(route('it-support-requests.store'), [
            ...$requestData,
            'attachments' => [UploadedFile::fake()->create('script.pdf', 1, 'text/plain')],
        ])
        ->assertSessionHasErrors('attachments.0');

    $this->post(route('it-support-requests.store'), [
        ...$requestData,
        'attachments' => [UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')],
    ])
        ->assertSessionHasErrors('attachments.0');

    $this->post(route('it-support-requests.store'), [
        ...$requestData,
        'attachments' => array_map(
            fn () => UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\nvalid"),
            range(1, 6),
        ),
    ])->assertSessionHasErrors('attachments');

    expect(ItSupportRequest::query()->count())->toBe(0);
});

it('requires authentication to submit a request', function () {
    $this->post(route('it-support-requests.store'), [])->assertRedirect(route('login'));
});
