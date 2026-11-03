<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $unmigratedPaths = DB::table('it_support_requests as requests')
            ->leftJoin('it_support_request_attachments as attachments', function ($join): void {
                $join->on('attachments.it_support_request_id', '=', 'requests.id')
                    ->on('attachments.file_path', '=', 'requests.attachment_path');
            })
            ->whereNotNull('requests.attachment_path')
            ->where('requests.attachment_path', '<>', '')
            ->whereNull('attachments.id')
            ->exists();

        if ($unmigratedPaths) {
            throw new \RuntimeException(
                'Cannot drop it_support_requests.attachment_path while legacy attachment references remain unmigrated.'
            );
        }

        Schema::table('it_support_requests', function (Blueprint $table): void {
            $table->dropColumn('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table): void {
            $table->string('attachment_path')->nullable();
        });

        DB::table('it_support_requests')
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($requests): void {
                foreach ($requests as $request) {
                    $filePath = DB::table('it_support_request_attachments')
                        ->where('it_support_request_id', $request->id)
                        ->orderBy('id')
                        ->value('file_path');

                    if ($filePath !== null) {
                        DB::table('it_support_requests')
                            ->where('id', $request->id)
                            ->update(['attachment_path' => $filePath]);
                    }
                }
            });
    }
};
