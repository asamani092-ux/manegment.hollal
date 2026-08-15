<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_task_templates', function (Blueprint $table) {
            $table->date('starts_on')->nullable()->after('is_active');
            $table->date('ends_on')->nullable()->after('starts_on');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_task_templates', function (Blueprint $table) {
            $table->dropColumn(['starts_on', 'ends_on']);
        });
    }
};
