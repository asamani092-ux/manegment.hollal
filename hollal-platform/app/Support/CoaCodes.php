<?php

namespace App\Support;

/**
 * رموز دليل الحسابات ذات الثلاث خانات (منشأة غير ربحية).
 * Time: O(1) | Space: O(1)
 */
final class CoaCodes
{
    public const CASH = '111';

    public const BANK_RAJHI = '112';

    public const BANK_INMA = '113';

    public const EMPLOYEE_ADVANCES = '114';

    public const RECEIVABLES = '115';

    public const FURNITURE = '121';

    public const COMPUTERS = '122';

    public const ACCUM_DEPRECIATION = '123';

    public const SALARIES_PAYABLE = '211';

    public const VAT_PAYABLE = '212';

    public const SOCIAL_INSURANCE_PAYABLE = '213';

    public const DEFERRED_REVENUE = '214';

    public const UNRESTRICTED_NET_ASSETS = '311';

    public const TEMP_RESTRICTED_NET_ASSETS = '321';

    public const PERM_RESTRICTED_NET_ASSETS = '322';

    public const UNRESTRICTED_PARTNERSHIP_REVENUE = '411';

    public const UNRESTRICTED_SERVICE_REVENUE = '412';

    public const UNRESTRICTED_OTHER_REVENUE = '413';

    public const RESTRICTED_PARTNERSHIP_REVENUE = '421';

    public const RESTRICTED_GRANT_REVENUE = '422';

    public const EXP_BAGS = '511';

    public const EXP_TRAINING = '512';

    public const EXP_VISITS = '513';

    public const EXP_CONSULTING = '514';

    public const EXP_LOGISTICS = '515';

    public const EXP_TECH = '516';

    public const EXP_MEDIA = '517';

    public const EXP_MISC = '518';

    public const EXP_SALARIES = '521';

    public const EXP_INSURANCE = '522';

    public const EXP_DELEGATION = '523';

    public const EXP_RENT = '531';

    public const EXP_OFFICE = '532';

    public const EXP_DEPRECIATION = '533';

    /** حسابات النقد والبنوك للمستوى الثالث. */
    public static function cashAndBanks(): array
    {
        return [self::CASH, self::BANK_RAJHI, self::BANK_INMA];
    }
}
