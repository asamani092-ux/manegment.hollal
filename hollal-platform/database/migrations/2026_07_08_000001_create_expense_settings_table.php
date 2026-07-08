<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_settings', function (Blueprint $table) {
            $table->id();
            $table->enum('chain_mode', ['full', 'short'])->default('full');
            $table->boolean('skip_missing_department_manager')->default(true);
            $table->timestamps();
        });

        DB::table('expense_settings')->insert([
            'chain_mode' => 'full',
            'skip_missing_department_manager' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_settings');
    }
};
