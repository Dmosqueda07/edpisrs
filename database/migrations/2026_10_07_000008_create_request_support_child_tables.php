<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_types', function (Blueprint $table) {
            $table->string('category')->nullable();
            $table->boolean('remote_allowed')->default(false);
        });

        Schema::table('it_support_request_comments', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false);
            $table->timestamp('read_at')->nullable();
        });

        Schema::create('it_support_request_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_support_request_id')
                ->constrained('it_support_requests')
                ->cascadeOnDelete();
            $table->string('field_key', 100);
            $table->string('field_label');
            $table->text('field_value');
        });

        Schema::create('it_support_request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_support_request_id')
                ->constrained('it_support_requests')
                ->cascadeOnDelete();
            $table->foreignId('uploaded_by')
                ->constrained('users', 'user_id')
                ->noActionOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 255);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        Schema::create('it_support_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_support_request_id')
                ->constrained('it_support_requests')
                ->noActionOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->noActionOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_support_request_logs');
        Schema::dropIfExists('it_support_request_attachments');
        Schema::dropIfExists('it_support_request_details');

        Schema::table('it_support_request_comments', function (Blueprint $table) {
            $table->dropColumn(['is_internal', 'read_at']);
        });

        Schema::table('request_types', function (Blueprint $table) {
            $table->dropColumn(['category', 'remote_allowed']);
        });
    }
};
