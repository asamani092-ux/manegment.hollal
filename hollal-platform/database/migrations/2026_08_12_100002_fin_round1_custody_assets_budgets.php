<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Round-1 FIN: custody rejection, asset description, org tax number, budget additions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custodies', function (Blueprint $table) {
            $table->string('rejection_reason')->nullable()->after('status');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name_ar');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('tax_number')->nullable()->after('city');
        });

        Schema::create('budget_additions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('revenue_id')->nullable()->constrained('revenues')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('note')->nullable();
            $table->string('status')->default('معلق');
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_additions');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('tax_number');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('description');
        });

        Schema::table('custodies', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
