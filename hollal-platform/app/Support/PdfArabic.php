<?php

namespace App\Support;

use App\Models\CompanyProfile;
use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Arabic-connected PDF chrome: Amiri font + glyph shaping + company logo + RTL.
 * Dompdf cannot join Arabic letters; utf8Glyphs pre-shapes text for correct print.
 * Font family key MUST stay lowercase `amiri` to match installed-fonts.json.
 * Time: O(n) HTML size | Space: O(n)
 */
final class PdfArabic
{
    private static ?Arabic $arabic = null;

    public static function fontPath(): ?string
    {
        $path = resource_path('fonts/Amiri-Regular.ttf');

        return is_file($path) ? $path : null;
    }

    /** Shape Arabic letters for Dompdf (disconnected → connected visual forms). */
    public static function shape(string $text): string
    {
        if ($text === '' || ! preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }

        // Already shaped to HTML entities by a prior pass.
        if (str_contains($text, '&#x')) {
            return $text;
        }

        // ar-php glyph table lacks Eastern Arabic digits — normalize first.
        $text = strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        try {
            self::$arabic ??= new Arabic;

            return self::$arabic->utf8Glyphs($text);
        } catch (\Throwable) {
            return $text;
        }
    }

    /** Shape text nodes inside HTML; leave tags/attributes untouched. */
    public static function shapeHtml(string $html): string
    {
        $shaped = preg_replace_callback(
            '/>([^<]*\p{Arabic}[^<]*)</u',
            static fn (array $m): string => '>'.self::shape($m[1]).'<',
            $html
        );

        return is_string($shaped) ? $shaped : $html;
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
            .'<p>'.e((string) $company->name).'</p>'
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
            // Subsetting can drop Arabic presentation forms after shaping.
            'isFontSubsettingEnabled' => false,
        ];
    }

    /**
     * Wrap body HTML with RTL Amiri chrome, shape Arabic, render PDF bytes.
     * Time: O(n) | Space: O(n)
     */
    public static function render(string $title, string $bodyHtml, string $paper = 'a4', bool $includeCr = false): string
    {
        $html = self::header($title, $includeCr)
            .'<div dir="rtl" style="unicode-bidi: embed;">'.$bodyHtml.'</div>';

        return self::outputFromHtml($html, $paper);
    }

    /** Apply standard Arabic PDF options to an existing Dompdf wrapper. */
    public static function applyOptions(mixed $pdf): mixed
    {
        foreach (self::pdfOptions() as $key => $value) {
            $pdf->setOption($key, $value);
        }

        return $pdf;
    }

    /**
     * Shape + option-apply for views/HTML already built (tax invoice, minutes, reports).
     */
    public static function outputFromHtml(string $html, string $paper = 'a4'): string
    {
        $pdf = Pdf::loadHTML(self::shapeHtml($html))->setPaper($paper);

        return self::applyOptions($pdf)->output();
    }
}
