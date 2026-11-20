<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyTables = [
            'it_support_request_approvals',
            'it_support_request_comments',
            'it_support_request_attachments',
        ];
        $presentLegacyTables = array_filter($legacyTables, fn (string $table): bool => Schema::hasTable($table));

        if ($presentLegacyTables === []
            && Schema::hasTable('support_requests')
            && Schema::hasTable('request_attachments')
            && Schema::hasTable('request_logs')) {
            return;
        }

        if (count($presentLegacyTables) !== count($legacyTables)) {
            throw new \RuntimeException('Cannot restrict legacy request history: the legacy request tables are incomplete.');
        }

        Schema::table('it_support_request_approvals', function (Blueprint $table): void {
            $table->dropForeign(['it_support_request_id']);
            $table->foreign('it_support_request_id')
                ->references('id')
                ->on('it_support_requests')
                ->noActionOnDelete();
        });

        Schema::table('it_support_request_comments', function (Blueprint $table): void {
            $table->dropForeign(['it_support_request_id']);
            $table->foreign('it_support_request_id')
                ->references('id')
                ->on('it_support_requests')
                ->noActionOnDelete();
        });

        Schema::table('it_support_request_attachments', function (Blueprint $table): void {
            $table->dropForeign(['it_support_request_id']);
            $table->foreign('it_support_request_id')
                ->references('id')
                ->on('it_support_requests')
                ->noActionOnDelete();
        });
    }

    public function down(): void
    {
        $legacyTables = [
            'it_support_request_approvals',
            'it_support_request_comments',
            'it_support_request_attachments',
        ];
        $presentLegacyTables = array_filter($legacyTables, fn (string $table): bool => Schema::hasTable($table));

        if ($presentLegacyTables === []
            && Schema::hasTable('support_requests')
            && Schema::hasTable('request_attachments')
            && Schema::hasTable('request_logs')) {
            return;
        }

        if (count($presentLegacyTables) !== count($legacyTables)) {
            throw new \RuntimeException('Cannot restore legacy request history: the legacy request tables are incomplete.');
        }

        Schema::table('it_support_request_attachments', function (Blueprint $table): void {
            $table->dropForeign(['it_support_request_id']);
            $table->foreign('it_support_request_id')
                ->references('id')
                ->on('it_support_requests')
                ->cascadeOnDelete();
        });

        Schema::table('it_support_request_comments', function (Blueprint $table): void {
            $table->dropForeign(['it_support_request_id']);
            $table->foreign('it_support_request_id')
                ->references('id')
                ->on('it_support_requests')
                ->cascadeOnDelete();
        });

        Schema::table('it_support_request_approvals', function (Blueprint $table): void {
            $table->dropForeign(['it_support_request_id']);
            $table->foreign('it_support_request_id')
                ->references('id')
                ->on('it_support_requests')
                ->cascadeOnDelete();
        });
    }
};
