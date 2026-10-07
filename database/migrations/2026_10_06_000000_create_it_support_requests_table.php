<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('it_support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')
                ->nullable()
                ->constrained('users', 'user_id')
                ->nullOnDelete();
            $table->string('requester_name');
            $table->string('division');
            $table->string('support_type');
            $table->text('details');
            $table->string('attachment_path')->nullable();
            $table->timestamp('certified_at');
            $table->string('status')->default('submitted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('it_support_requests');
    }
};
