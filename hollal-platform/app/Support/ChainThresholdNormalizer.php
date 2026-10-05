<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * يحوّل عتبة gte ذات الكسر 0.01 إلى gt على العدد الصحيح. التكرار لا يغيّر الناتج.
 * Time: O(steps) | Space: O(1)
 */
class ChainThresholdNormalizer
{
    public static function run(): void
    {
        if (! Schema::hasTable('approval_chain_steps')) {
            return;
        }

        DB::table('approval_chain_steps')
            ->where('condition_operator', 'gte')
            ->whereNotNull('condition_value')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $scaled = (int) round(((float) $row->condition_value) * 100);
                    if ($scaled % 100 !== 1) {
                        continue;
                    }
                    DB::table('approval_chain_steps')->where('id', $row->id)->update([
                        'condition_operator' => 'gt',
                        'condition_value' => intdiv($scaled, 100),
                    ]);
                }
            });
    }
}
