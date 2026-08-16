<?php

namespace App\Support;

use App\Models\CompanyProfile;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Arabic-connected PDF chrome: Amiri font + company logo + RTL table.
 * Font family key MUST stay lowercase `amiri` to match installed-fonts.json.
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

        return '@font-face { font-family: amiri; font-style: normal; font-weight: normal; src: url("'.$src.'") format("truetype"); }'
            .' body { font-family: amiri, DejaVu Sans, sans-serif; direction: rtl; unicode-bidi: embed; }';
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
        $address = $includeCr && $company->address
            ? '<p>العنوان: '.e((string) $company->address).'</p>'
            : '';
        $tax = $company->tax_number
            ? '<p>الرقم الضريبي: '.e((string) $company->tax_number).'</p>'
            : '';

        return '<div dir="rtl" style="text-align:right; unicode-bidi: embed;">'
            .'<style>'.self::fontFace().' table { border-collapse: collapse; width: 100%; }'
            .' th, td { border: 1px solid #0F3446; padding: 6px; } th { background: #0F3446; color: #fff; }</style>'
            .$logo
            .'<h2>'.e($title).'</h2>'
            .'<p>'.e($company->name).'</p>'
            .$tax
            .$cr
            .$address
            .'</div>';
    }

    public static function defaultFont(): string
    {
        // Must match storage/fonts/installed-fonts.json family key (lowercase).
        return self::fontPath() !== null ? 'amiri' : 'dejavu sans';
    }

    /** @return array<string, mixed> */
    public static function pdfOptions(): array
    {
        return [
            'defaultFont' => self::defaultFont(),
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isFontSubsettingEnabled' => true,
        ];
    }

    /**
     * Wrap body HTML with RTL Amiri chrome and render PDF bytes.
     * Time: O(n) | Space: O(n)
     */
    public static function render(string $title, string $bodyHtml, string $paper = 'a4', bool $includeCr = false): string
    {
        $html = self::header($title, $includeCr)
            .'<div dir="rtl" style="unicode-bidi: embed;">'.$bodyHtml.'</div>';

        $pdf = Pdf::loadHTML($html)->setPaper($paper);
        foreach (self::pdfOptions() as $key => $value) {
            $pdf->setOption($key, $value);
        }

        return $pdf->output();
    }

    /** Apply standard Arabic PDF options to an existing Dompdf wrapper. */
    public static function applyOptions(mixed $pdf): mixed
    {
        foreach (self::pdfOptions() as $key => $value) {
            $pdf->setOption($key, $value);
        }

        return $pdf;
    }
}
