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
    public static function fontPath(): ?string
    {
        $path = resource_path('fonts/Amiri-Regular.ttf');

        return is_file($path) ? $path : null;
    }

    public static function fontFace(): string
    {
        $path = self::fontPath();
        if ($path === null) {
            return 'body { font-family: dejavu sans, sans-serif; direction: rtl; unicode-bidi: embed; }';
        }

        $src = 'file://'.str_replace('\\', '/', $path);

        return '@font-face { font-family: Amiri; font-style: normal; font-weight: normal; src: url("'.$src.'") format("truetype"); }'
            .' body { font-family: Amiri, dejavu sans, sans-serif; direction: rtl; unicode-bidi: embed; }';
    }

    public static function header(string $title, bool $includeCr = false): string
    {
        $company = CompanyProfile::current();
        $logo = '';
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $full = Storage::disk('public')->path($company->logo_path);
            $logo = '<img src="file://'.str_replace('\\', '/', $full).'" alt="" style="height:48px;">';
        } elseif ($company->logo_path && is_file(public_path($company->logo_path))) {
            $logo = '<img src="file://'.str_replace('\\', '/', public_path($company->logo_path)).'" alt="" style="height:48px;">';
        }

        $cr = $includeCr && $company->commercial_register
            ? '<p>السجل التجاري: '.e((string) $company->commercial_register).'</p>'
            : '';

        return '<div dir="rtl" style="text-align:right; unicode-bidi: embed;">'
            .'<style>'.self::fontFace().' table { border-collapse: collapse; width: 100%; }'
            .' th, td { border: 1px solid #0F3446; padding: 6px; } th { background: #0F3446; color: #fff; }</style>'
            .$logo
            .'<h2>'.e($title).'</h2>'
            .'<p>'.e($company->name).' — الرقم الضريبي: '.e((string) $company->tax_number).'</p>'
            .$cr
            .'</div>';
    }

    public static function defaultFont(): string
    {
        return self::fontPath() !== null ? 'Amiri' : 'dejavu sans';
    }

    /** @return array<string, mixed> */
    public static function pdfOptions(): array
    {
        return [
            'defaultFont' => self::defaultFont(),
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ];
    }
}
