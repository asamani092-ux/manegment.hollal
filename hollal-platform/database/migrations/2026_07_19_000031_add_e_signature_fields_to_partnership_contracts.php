<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Amendments Q2 — e-signature inside partner link alongside upload.
 * Time: O(1) schema | Space: O(1)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partnership_contracts', function (Blueprint $table) {
            $table->string('signature_method')->nullable()->after('signature_name'); // داخل_الرابط|رفع_يدوي
            $table->string('signature_position')->nullable()->after('signature_method');
            $table->string('signature_image_path')->nullable()->after('signature_position');
        });
    }

    public function down(): void
    {
        Schema::table('partnership_contracts', function (Blueprint $table) {
            $table->dropColumn(['signature_method', 'signature_position', 'signature_image_path']);
        });
    }
};
