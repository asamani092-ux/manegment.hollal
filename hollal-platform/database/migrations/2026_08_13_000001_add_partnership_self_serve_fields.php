<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnerships', function (Blueprint $table) {
            $table->boolean('awaiting_internal_approval')->default(false)->after('status');
            $table->text('internal_approval_notes')->nullable()->after('awaiting_internal_approval');
        });

        Schema::create('partnership_allowed_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partnership_id', 'program_id']);
            $table->index(['program_id', 'partnership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partnership_allowed_programs');

        Schema::table('partnerships', function (Blueprint $table) {
            $table->dropColumn(['awaiting_internal_approval', 'internal_approval_notes']);
        });
    }
};
