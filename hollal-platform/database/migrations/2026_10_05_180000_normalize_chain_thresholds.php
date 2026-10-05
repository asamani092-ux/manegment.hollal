<?php

use App\Support\ChainThresholdNormalizer;
use Illuminate\Database\Migrations\Migration;

/**
 * يحوّل gte بقيمة X.01 إلى gt بقيمة X. التشغيل المتكرر لا يغيّر الصفوف المحوّلة.
 * Time: O(steps) | Space: O(1)
 */
return new class extends Migration
{
    public function up(): void
    {
        ChainThresholdNormalizer::run();
    }

    public function down(): void
    {
        // العتبة gt X قد تكون أصلية وليست ناتج هذا الترحيل.
    }
};
