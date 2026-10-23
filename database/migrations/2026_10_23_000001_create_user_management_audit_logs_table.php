<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_management_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->constrained('users', 'user_id')->noActionOnDelete();
            $table->foreignId('target_user_id')->constrained('users', 'user_id')->noActionOnDelete();
            $table->string('action', 64);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('actor_user_id', 'user_management_audit_actor_index');
            $table->index('target_user_id', 'user_management_audit_target_index');
            $table->index('action', 'user_management_audit_action_index');
            $table->index('created_at', 'user_management_audit_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_management_audit_logs');
    }
};
