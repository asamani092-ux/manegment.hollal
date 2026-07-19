<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 06A-B1 — full program card: service prices, private-disk files, platform
 * steps, and version history (who changed what, why, and who approved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->text('platform_steps')->nullable()->after('platform_notes');
            $table->unsignedBigInteger('current_version_id')->nullable()->after('platform_steps');
        });

        Schema::table('program_versions', function (Blueprint $table) {
            $table->foreignId('changed_by')->nullable()->after('version_label')->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable()->after('changed_by');
            $table->boolean('is_current')->default(false)->after('change_reason');
            $table->json('snapshot')->nullable()->after('is_current');
        });

        Schema::create('program_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('service_type'); // حقيبة|تدريب|زيارة|استشارة|قياس
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->string('currency', 8)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['program_id', 'service_type']);
        });

        Schema::create('program_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('kind')->default('أخرى'); // كتاب|دليل المعلم|حقيبة|أخرى
            $table->string('path'); // private disk
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_files');
        Schema::dropIfExists('program_prices');

        Schema::table('program_versions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('changed_by');
            $table->dropColumn(['change_reason', 'is_current', 'snapshot']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['platform_steps', 'current_version_id']);
        });
    }
};
