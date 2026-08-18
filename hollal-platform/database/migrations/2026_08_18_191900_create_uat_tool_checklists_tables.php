<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uat_tool_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('slot', 32)->unique();
            $table->unsignedTinyInteger('active_phase')->default(1);
            $table->json('verdicts');
            $table->json('tags');
            $table->json('notes');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('uat_tool_checklist_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('uat_tool_checklists')->cascadeOnDelete();
            $table->string('source', 64);
            $table->unsignedTinyInteger('active_phase');
            $table->json('verdicts');
            $table->json('tags');
            $table->json('notes');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['checklist_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uat_tool_checklist_snapshots');
        Schema::dropIfExists('uat_tool_checklists');
    }
};
