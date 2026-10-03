<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_units', function (Blueprint $table) {
            $table->string('default_role')->nullable()->after('name');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('auto_role_name')->nullable()->after('org_unit_id');
        });
        Schema::create('employee_onboarding_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reference_item_id')->constrained('reference_items')->cascadeOnDelete();
            $table->string('status', 30)->default('open');
            $table->timestamps();
            $table->unique(['user_id', 'reference_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_onboarding_items');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('auto_role_name');
        });
        Schema::table('org_units', function (Blueprint $table) {
            $table->dropColumn('default_role');
        });
    }
};
