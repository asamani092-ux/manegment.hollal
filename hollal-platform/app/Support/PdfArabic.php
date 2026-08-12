<?php

namespace App\Support;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;

/**
 * Arabic-connected PDF chrome: Amiri font + company logo + RTL table.
 * Time: O(n) HTML size | Space: O(n)
 */
final class PdfArabic
{
    public static function fontFace(): string
    {
        $path = resource_path('fonts/Amiri-Regular.ttf');
        if (! is_file($path)) {
            return 'body { font-family: dejavu sans, sans-serif; }';
        }

        return '@font-face { font-family: Amiri; src: url("'.$path.'") format("truetype"); }'
            .' body { font-family: Amiri, dejavu sans, sans-serif; }';
    }

    public static function header(string $title): string
    {
        $company = CompanyProfile::current();
        $logo = '';
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $full = Storage::disk('public')->path($company->logo_path);
            $logo = '<img src="'.$full.'" alt="" style="height:48px;">';
        } elseif ($company->logo_path && is_file(public_path($company->logo_path))) {
            $logo = '<img src="'.public_path($company->logo_path).'" alt="" style="height:48px;">';
        }

        return '<div dir="rtl" style="text-align:right;">'
            .'<style>'.self::fontFace().' table { border-collapse: collapse; width: 100%; }'
            .' th, td { border: 1px solid #0F3446; padding: 6px; } th { background: #0F3446; color: #fff; }</style>'
            .$logo
            .'<h2>'.e($title).'</h2>'
            .'<p>'.e($company->name).' — الرقم الضريبي: '.e((string) $company->tax_number).'</p>'
            .'</div>';
    }

    public static function defaultFont(): string
    {
        return is_file(resource_path('fonts/Amiri-Regular.ttf')) ? 'Amiri' : 'dejavu sans';
    }
}
