<?php

namespace App\Support;

use App\Models\CompanyProfile;
use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Arabic-connected PDF chrome: Amiri font + glyph shaping + company logo.
 *
 * Dompdf has no real bidi/joining. Presentation forms from ar-php must be
 * laid out LTR (text-align:right). Numbers/Latin stay outside Arabic runs
 * so utf8Glyphs never reverses VAT IDs or amounts.
 *
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

    /**
     * Shape Arabic letter runs only; keep digits, Latin, and punctuation intact.
     * Time: O(n) | Space: O(n)
     */
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

        $shaped = preg_replace_callback(
            '/\p{Arabic}(?:[\p{Arabic}\p{Mn}\s]*\p{Arabic})?/u',
            static fn (array $m): string => self::shapeArabicRun($m[0]),
            $text
        );

        return is_string($shaped) ? $shaped : $text;
    }

    /** utf8Glyphs on an Arabic-only fragment (hindo=false → Western digits untouched). */
    private static function shapeArabicRun(string $run): string
    {
        try {
            self::$arabic ??= new Arabic;
            $prev = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
            try {
                $max = max(50, mb_strlen($run) + 10);

                return self::$arabic->utf8Glyphs($run, $max, false);
            } finally {
                error_reporting($prev);
            }
        } catch (\Throwable) {
            return $run;
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
        // Dompdf: presentation-form Arabic must flow LTR; align to the right for RTL reading.
        $body = 'body { direction: ltr; text-align: right; unicode-bidi: isolate; }'
            .' table { width: 100%; border-collapse: collapse; direction: ltr; margin: 8px 0; }'
            .' th, td { border: 1px solid #0F3446; padding: 6px 8px; text-align: right; vertical-align: middle; }'
            .' th { background: #0F3446; color: #fff; }'
            .' td.num, th.num, .pdf-num { text-align: left; direction: ltr; unicode-bidi: isolate; font-family: DejaVu Sans, amiri, sans-serif; }'
            .' td.pdf-label { width: 32%; white-space: nowrap; font-weight: bold; text-align: right; }'
            .' table.pdf-meta td.pdf-label { background: #f3f6f8; }';

        if ($path === null) {
            return 'body, table, th, td { font-family: dejavu sans, sans-serif; }'.$body;
        }

        $src = 'file://'.str_replace('\\', '/', $path);

        return '@font-face { font-family: amiri; font-style: normal; font-weight: normal; src: url("'.$src.'") format("truetype"); }'
            .' body, table, th, td { font-family: amiri, DejaVu Sans, sans-serif; }'
            .$body;
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
            ? '<p>السجل التجاري: <span class="pdf-num">'.e((string) $company->commercial_register).'</span></p>'
            : '';
        $address = $includeCr && $company->address
            ? '<p>العنوان: '.e((string) $company->address).'</p>'
            : '';
        $tax = $company->tax_number
            ? '<p>الرقم الضريبي: <span class="pdf-num">'.e((string) $company->tax_number).'</span></p>'
            : '';

        return '<div style="text-align:right;">'
            .'<style>'.self::fontFace().'</style>'
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
            // Keep subsetting on so PDFs stay small; shaping uses presentation forms that Amiri embeds.
            'isFontSubsettingEnabled' => true,
        ];
    }

    /**
     * Wrap body HTML with Amiri chrome, shape Arabic, render PDF bytes.
     * Time: O(n) | Space: O(n)
     */
    public static function render(string $title, string $bodyHtml, string $paper = 'a4', bool $includeCr = false): string
    {
        $html = self::header($title, $includeCr)
            .'<div style="text-align:right;">'.$bodyHtml.'</div>';

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
