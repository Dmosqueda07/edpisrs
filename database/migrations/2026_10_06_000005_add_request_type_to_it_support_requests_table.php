<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->foreignId('request_type_id')
                ->nullable()
                ->after('requester_user_id')
                ->constrained('request_types')
                ->nullOnDelete();
            $table->string('follow_up_question')->nullable()->after('support_type');
            $table->boolean('requires_approval')->default(false)->after('status');
            $table->json('approval_roles')->nullable()->after('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('request_type_id');
            $table->dropColumn(['follow_up_question', 'requires_approval', 'approval_roles']);
        });
    }
};
