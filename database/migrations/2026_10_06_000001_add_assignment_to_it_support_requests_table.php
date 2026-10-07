<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->foreignId('assigned_technician_id')
                ->nullable()
                ->after('requester_user_id')
                ->constrained('users', 'user_id');
            $table->timestamp('assigned_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('it_support_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_technician_id');
            $table->dropColumn('assigned_at');
        });
    }
};
