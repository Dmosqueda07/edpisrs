<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('assigned_at');
            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->after('started_at')
                ->constrained('users', 'user_id');
            $table->timestamp('resolved_at')->nullable()->after('resolved_by_user_id');
            $table->timestamp('completed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['started_at', 'resolved_at', 'completed_at']);
        });
    }
};
