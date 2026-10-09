<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $sqlServer = DB::connection()->getDriverName() === 'sqlsrv';
        $lengthLimits = [
            ['users', 'email', 450, 225],
            ['users', 'role', 128, 64],
            ['users', 'division', 64, 32],
            ['it_support_requests', 'status', 64, 32],
            ['it_support_requests', 'division', 64, 32],
            ['request_types', 'key', 200, 100],
        ];

        foreach ($lengthLimits as [$table, $column, $maxBytes, $maxCharacters]) {
            $limit = $sqlServer ? $maxBytes : $maxCharacters;
            $lengthExpression = $sqlServer ? "DATALENGTH([$column])" : "length([$column])";

            if (DB::table($table)->whereRaw("{$lengthExpression} > ?", [$limit])->exists()) {
                throw new \RuntimeException("Cannot shorten {$table}.{$column}: existing values exceed the approved indexed length.");
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->string('email', 225)->change();
            $table->string('role', 64)->change();
            $table->string('division', 32)->change();
            $table->unique('email', 'users_email_unique');
            $table->index('role', 'users_role_index');
            $table->index('division', 'users_division_index');
        });

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->string('status', 32)->change();
            $table->string('division', 32)->change();
            $table->index('status', 'it_support_requests_status_index');
            $table->index('priority', 'it_support_requests_priority_index');
            $table->index('assigned_technician_id', 'it_support_requests_assigned_technician_index');
            $table->index('requester_user_id', 'it_support_requests_requester_user_index');
            $table->index('request_type_id', 'it_support_requests_request_type_index');
            $table->index('division', 'it_support_requests_division_index');
            $table->index('requested_at', 'it_support_requests_requested_at_index');
            $table->index('resolved_by_user_id', 'it_support_requests_resolved_by_index');
        });

        Schema::table('request_types', function (Blueprint $table) {
            $table->dropUnique('request_types_key_unique');
            $table->string('key', 100)->change();
            $table->unique('key', 'request_types_key_unique');
        });

        Schema::table('it_support_request_comments', function (Blueprint $table) {
            $table->index('it_support_request_id', 'it_support_request_comments_request_index');
            $table->index('user_id', 'it_support_request_comments_user_index');
        });

        Schema::table('it_support_request_approvals', function (Blueprint $table) {
            $table->index('approver_user_id', 'it_support_request_approvals_approver_index');
        });

        Schema::table('it_support_request_details', function (Blueprint $table) {
            $table->index('it_support_request_id', 'it_support_request_details_request_index');
        });

        Schema::table('it_support_request_attachments', function (Blueprint $table) {
            $table->index('it_support_request_id', 'it_support_request_attachments_request_index');
            $table->index('uploaded_by', 'it_support_request_attachments_uploaded_by_index');
        });

        Schema::table('it_support_request_logs', function (Blueprint $table) {
            $table->index('it_support_request_id', 'it_support_request_logs_request_index');
            $table->index('user_id', 'it_support_request_logs_user_index');
        });
    }

    public function down(): void
    {
        Schema::table('it_support_request_logs', function (Blueprint $table) {
            $table->dropIndex('it_support_request_logs_request_index');
            $table->dropIndex('it_support_request_logs_user_index');
        });

        Schema::table('it_support_request_attachments', function (Blueprint $table) {
            $table->dropIndex('it_support_request_attachments_request_index');
            $table->dropIndex('it_support_request_attachments_uploaded_by_index');
        });

        Schema::table('it_support_request_details', function (Blueprint $table) {
            $table->dropIndex('it_support_request_details_request_index');
        });

        Schema::table('it_support_request_approvals', function (Blueprint $table) {
            $table->dropIndex('it_support_request_approvals_approver_index');
        });

        Schema::table('it_support_request_comments', function (Blueprint $table) {
            $table->dropIndex('it_support_request_comments_request_index');
            $table->dropIndex('it_support_request_comments_user_index');
        });

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->dropIndex('it_support_requests_status_index');
            $table->dropIndex('it_support_requests_priority_index');
            $table->dropIndex('it_support_requests_assigned_technician_index');
            $table->dropIndex('it_support_requests_requester_user_index');
            $table->dropIndex('it_support_requests_request_type_index');
            $table->dropIndex('it_support_requests_division_index');
            $table->dropIndex('it_support_requests_requested_at_index');
            $table->dropIndex('it_support_requests_resolved_by_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_index');
            $table->dropIndex('users_division_index');
            $table->dropUnique('users_email_unique');
            $table->string('email', 255)->change();
            $table->string('role', 255)->change();
            $table->string('division', 255)->change();
            $table->unique('email', 'users_email_unique');
        });

        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->string('status', 255)->change();
            $table->string('division', 255)->change();
        });

        Schema::table('request_types', function (Blueprint $table) {
            $table->dropUnique('request_types_key_unique');
            $table->string('key', 255)->change();
            $table->unique('key', 'request_types_key_unique');
        });
    }
};
