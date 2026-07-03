<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->date('week_end');
            $table->json('done')->nullable();
            $table->json('overdue')->nullable();
            $table->json('project_status')->nullable();
            $table->decimal('week_spend', 14, 2)->default(0);
            $table->json('open_decisions')->nullable();
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['week_start', 'week_end']);
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
