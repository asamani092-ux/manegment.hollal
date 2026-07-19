<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 04-B5 — assets + their movement timeline. Every تسليم/استلام/صيانة/استبعاد is
 * an immutable movement row with an Arabic handover PDF on the private disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ar');
            $table->foreignId('category_id')->nullable()->constrained('asset_categories')->nullOnDelete();
            $table->boolean('can_be_custody')->default(false);
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_amount', 12, 2)->nullable();
            $table->foreignId('purchase_expense_id')->nullable()->constrained('expense_requests')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('condition')->default('جيد'); // جيد|صيانة|تالف|مستبعد
            $table->foreignId('current_holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('holder_since')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('asset_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_holder_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moved_at')->useCurrent();
            $table->string('reason')->nullable();
            $table->string('handover_document_path')->nullable();
            $table->string('movement_type'); // تسليم|استلام|صيانة|استبعاد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_movements');
        Schema::dropIfExists('assets');
    }
};
