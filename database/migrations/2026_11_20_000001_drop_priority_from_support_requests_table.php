<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->dropIndex('support_requests_priority_index');
            $table->dropColumn('priority');
        });
    }

    public function down(): void
    {
        Schema::table('support_requests', function (Blueprint $table): void {
            $table->string('priority', 20)->default('normal');
            $table->index('priority', 'support_requests_priority_index');
        });
    }
};
