<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
