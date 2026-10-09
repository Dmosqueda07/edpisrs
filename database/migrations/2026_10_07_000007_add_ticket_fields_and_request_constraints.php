<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullRequesters = DB::table('it_support_requests')
            ->whereNull('requester_user_id')
            ->count();
        $nullRequestTypes = DB::table('it_support_requests')
            ->whereNull('request_type_id')
            ->count();
        $duplicateApprovals = DB::table('it_support_request_approvals')
            ->select('it_support_request_id')
            ->groupBy('it_support_request_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($nullRequesters > 0 || $nullRequestTypes > 0 || $duplicateApprovals !== null) {
            throw new \RuntimeException(
                'Cannot tighten request constraints: resolve null requester/type references and duplicate approval rows first.'
            );
        }

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->string('ticket_no', 20)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('summary')->nullable();
            $table->text('details')->nullable()->change();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('requester_confirmed_at')->nullable();
        });

        $sequences = [];
        DB::table('it_support_requests')
            ->select(['id', 'created_at', 'support_type'])
            ->orderBy('id')
            ->chunkById(500, function ($requests) use (&$sequences): void {
                foreach ($requests as $request) {
                    $requestedAt = $request->created_at
                        ? CarbonImmutable::parse($request->created_at)
                        : CarbonImmutable::now();
                    $year = $requestedAt->year;
                    $sequences[$year] = ($sequences[$year] ?? 0) + 1;

                    DB::table('it_support_requests')
                        ->where('id', $request->id)
                        ->update([
                            'ticket_no' => sprintf('EDP-%04d-%05d', $year, $sequences[$year]),
                            'summary' => $request->support_type ?: 'IT support request',
                            'requested_at' => $requestedAt,
                        ]);
                }
            });

        DB::table('it_support_requests')
            ->where('status', 'completed')
            ->update(['status' => 'resolved']);

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->string('ticket_no', 20)->nullable(false)->change();
            $table->string('summary')->nullable(false)->change();
            $table->timestamp('requested_at')->nullable(false)->change();

            $table->dropForeign(['requester_user_id']);
            $table->dropForeign(['request_type_id']);
            $table->unsignedBigInteger('requester_user_id')->nullable(false)->change();
            $table->unsignedBigInteger('request_type_id')->nullable(false)->change();
            $table->foreign('requester_user_id')
                ->references('user_id')
                ->on('users')
                ->noActionOnDelete();
            $table->foreign('request_type_id')
                ->references('id')
                ->on('request_types')
                ->noActionOnDelete();
            $table->unique('ticket_no');
        });

        Schema::table('it_support_request_approvals', function (Blueprint $table) {
            $table->dropUnique(['it_support_request_id', 'approver_user_id']);
            $table->unique('it_support_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('it_support_request_approvals', function (Blueprint $table) {
            $table->dropUnique(['it_support_request_id']);
            $table->unique(['it_support_request_id', 'approver_user_id']);
        });

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->dropUnique(['ticket_no']);
            $table->dropForeign(['requester_user_id']);
            $table->dropForeign(['request_type_id']);
            $table->unsignedBigInteger('requester_user_id')->nullable()->change();
            $table->unsignedBigInteger('request_type_id')->nullable()->change();
            $table->foreign('requester_user_id')
                ->references('user_id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('request_type_id')
                ->references('id')
                ->on('request_types')
                ->nullOnDelete();
        });

        DB::table('it_support_requests')
            ->where('status', 'closed')
            ->update(['status' => 'completed']);
        DB::table('it_support_requests')
            ->whereIn('status', ['approved', 'on_hold', 'cancelled'])
            ->update(['status' => 'submitted']);
        DB::table('it_support_requests')
            ->whereNull('details')
            ->update(['details' => '']);

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->text('details')->nullable(false)->change();
            $table->dropColumn([
                'ticket_no',
                'priority',
                'summary',
                'requested_at',
                'closed_at',
                'resolution_notes',
                'requester_confirmed_at',
            ]);
        });
    }
};
