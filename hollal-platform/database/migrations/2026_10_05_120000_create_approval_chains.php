<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سلسلة واحدة لكل نوع طلب. الجداول القديمة تبقى.
 * Time: O(rules) | Space: O(steps)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approval_chains')) {
            Schema::create('approval_chains', function (Blueprint $table) {
                $table->id();
                $table->string('request_type')->unique();
                $table->boolean('is_active')->default(true);
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('approval_chain_steps')) {
            Schema::create('approval_chain_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_chain_id')->constrained('approval_chains')->cascadeOnDelete();
                $table->unsignedInteger('position');
                $table->string('approver_type');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->json('user_ids')->nullable();
                $table->string('condition_operator')->nullable();
                $table->decimal('condition_value', 15, 2)->nullable();
                $table->string('on_unresolved')->default('skip');
                $table->string('label_ar')->nullable();
                $table->timestamps();
                $table->unique(['approval_chain_id', 'position']);
            });
        }

        if (Schema::hasTable('approval_rules')) {
            app(\App\Services\Approval\ApprovalChainDeriver::class)->syncAll();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chain_steps');
        Schema::dropIfExists('approval_chains');
    }
};
