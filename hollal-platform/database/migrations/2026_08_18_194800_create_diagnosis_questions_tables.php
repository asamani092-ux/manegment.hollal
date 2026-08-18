<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diagnosis_questions', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->nullable()->unique();
            $table->string('label');
            $table->string('type', 16)->default('text');
            $table->boolean('required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('diagnosis_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partnership_id')->constrained('partnerships')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('diagnosis_questions')->cascadeOnDelete();
            $table->text('value');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['partnership_id', 'question_id', 'created_at']);
        });

        $now = now();
        DB::table('diagnosis_questions')->insert([
            ['key' => 'audience', 'label' => 'الفئة', 'type' => 'text', 'required' => true, 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'count', 'label' => 'الأعداد', 'type' => 'number', 'required' => true, 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'environment', 'label' => 'البيئة', 'type' => 'textarea', 'required' => false, 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnosis_answers');
        Schema::dropIfExists('diagnosis_questions');
    }
};
