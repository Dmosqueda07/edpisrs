<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function signatureUpload(string $name = 'signature.png', int $size = 0): UploadedFile
{
    $content = file_get_contents(base_path('vendor/livewire/livewire/src/Features/SupportFileUploads/browser_test_image.png'));

    if ($size > strlen($content)) {
        $content .= str_repeat('A', $size - strlen($content));
    }

    return UploadedFile::fake()->createWithContent($name, $content);
}

beforeEach(function () {
    Storage::fake('private');
});

it('stores a valid signature image on the private disk and serves it only to its owner and administrators', function () {
    $owner = User::factory()->create();
    $staff = User::factory()->create();
    $administrator = User::factory()->create(['role' => Role::Administrator]);

    $this->actingAs($owner)->post(route('profile.signature.store'), [
        'signature' => signatureUpload(),
    ])->assertRedirect();

    $owner->refresh();
    expect($owner->signature_path)->not->toBeNull();
    Storage::disk('private')->assertExists($owner->signature_path);

    $this->get(route('users.signature', $owner))->assertOk();
    $this->actingAs($staff)->get(route('users.signature', $owner))->assertForbidden();
    $this->actingAs($administrator)->get(route('users.signature', $owner))->assertOk();
});

it('rejects signatures that are not PNG or JPG images or exceed one megabyte', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('profile.signature.store'), [
        'signature' => UploadedFile::fake()->createWithContent('signature.txt', 'not an image'),
    ])->assertSessionHasErrors(['signature']);

    $this->post(route('profile.signature.store'), [
        'signature' => signatureUpload('oversized.png', 1024 * 1024 + 1),
    ])->assertSessionHasErrors(['signature']);

    expect($owner->refresh()->signature_path)->toBeNull();
});

it('requires authentication to upload or retrieve a signature', function () {
    $user = User::factory()->create();

    $this->post(route('profile.signature.store'), [
        'signature' => signatureUpload('signature.jpg'),
    ])->assertRedirect(route('login'));

    $this->get(route('users.signature', $user))->assertRedirect(route('login'));
});
