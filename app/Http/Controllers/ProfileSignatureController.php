<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileSignatureController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'signature' => ['required', 'image', 'mimes:jpg,jpeg,png', 'mimetypes:image/jpeg,image/png', 'max:1024'],
        ]);

        $disk = Storage::disk('private');
        $oldPath = $user->signature_path;
        $newPath = $validated['signature']->store('signatures/'.$user->getKey(), 'private');
        $user->forceFill(['signature_path' => $newPath])->save();

        if ($oldPath !== null && $oldPath !== $newPath && $disk->exists($oldPath) && ! $disk->delete($oldPath)) {
            Log::warning('Unable to remove replaced profile signature.', [
                'user_id' => $user->getKey(),
                'signature_path' => $oldPath,
            ]);
        }

        return back()->with('status', 'Signature uploaded.');
    }

    public function show(User $user): StreamedResponse
    {
        $this->authorize('viewSignature', $user);

        abort_if($user->signature_path === null, 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($user->signature_path), 404);

        $mimeType = $disk->mimeType($user->signature_path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png'], true), 404);

        return $disk->response($user->signature_path, null, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
