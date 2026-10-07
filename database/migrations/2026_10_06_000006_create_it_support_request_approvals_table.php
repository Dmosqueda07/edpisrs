<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_support_request_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('it_support_request_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('approver_user_id')
                ->nullable()
                ->constrained('users', 'user_id');
            $table->string('approver_name');
            $table->string('approver_role');
            $table->string('decision');
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['it_support_request_id', 'approver_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_support_request_approvals');
    }
};
