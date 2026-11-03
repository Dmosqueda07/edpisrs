<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $disk = Storage::disk('private');

        DB::transaction(function () use ($disk): void {
            DB::table('it_support_requests')
                ->whereNotNull('attachment_path')
                ->where('attachment_path', '<>', '')
                ->orderBy('id')
                ->chunkById(100, function ($requests) use ($disk): void {
                    foreach ($requests as $request) {
                        $alreadyMigrated = DB::table('it_support_request_attachments')
                            ->where('it_support_request_id', $request->id)
                            ->where('file_path', $request->attachment_path)
                            ->exists();

                        if ($alreadyMigrated) {
                            continue;
                        }

                        if (! $disk->exists($request->attachment_path)) {
                            throw new \RuntimeException(
                                "Cannot migrate attachment for request {$request->id}: the file is missing from the private disk."
                            );
                        }

                        $mimeType = $disk->mimeType($request->attachment_path);
                        $size = $disk->size($request->attachment_path);

                        if (! is_string($mimeType) || $mimeType === '' || ! is_int($size)) {
                            throw new \RuntimeException(
                                "Cannot migrate attachment for request {$request->id}: file metadata could not be read."
                            );
                        }

                        DB::table('it_support_request_attachments')->insert([
                            'it_support_request_id' => $request->id,
                            'uploaded_by' => $request->requester_user_id,
                            'file_path' => $request->attachment_path,
                            'original_name' => basename($request->attachment_path),
                            'mime_type' => $mimeType,
                            'size' => $size,
                            'created_at' => $request->created_at ?? now(),
                            'updated_at' => $request->updated_at ?? now(),
                        ]);
                    }
                });
        });
    }

    public function down(): void
    {
        // Keep migrated attachment metadata when rolling back to avoid losing the backfill.
    }
};
