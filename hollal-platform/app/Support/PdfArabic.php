<?php

namespace App\Support;

use App\Models\CompanyProfile;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

/**
 * Arabic RTL PDF engine using mPDF — native bidi, joining, and RTL support.
 * No shaping hacks needed. HTML is rendered as-is with dir="rtl".
 *
 * Time: O(n) HTML size | Space: O(n)
 */
final class PdfArabic
{
    /**
     * Create a configured mPDF instance with Arabic font and RTL defaults.
     */
    public static function createMpdf(string $paper = 'A4', string $orientation = 'P'): Mpdf
    {
        $defaultConfig = (new ConfigVariables)->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new FontVariables)->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $tempDir = storage_path('app/mpdf-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => strtoupper($paper) === 'A4' || strtolower($paper) === 'a4' ? 'A4' : $paper,
            'orientation' => $orientation,
            'default_font_size' => 12,
            'default_font' => 'ibmplex',
            'directionality' => 'rtl',
            'margin_top' => 18,
            'margin_bottom' => 18,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_header' => 8,
            'margin_footer' => 8,
            'fontDir' => array_merge($fontDirs, [
                resource_path('fonts'),
            ]),
            'fontdata' => $fontData + [
                'ibmplex' => [
                    'R' => 'IBMPlexSansArabic-Regular.ttf',
                    'B' => 'IBMPlexSansArabic-Bold.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
                'amiri' => [
                    'R' => 'Amiri-Regular.ttf',
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => $tempDir,
        ]);
    }

    /**
     * Standard CSS for all PDFs — Hollal brand identity.
     */
    public static function standardCss(): string
    {
        return '
            body {
                font-family: ibmplex, sans-serif;
                font-size: 12px;
                line-height: 1.7;
                color: #2a3f5f;
                direction: rtl;
                text-align: right;
            }
            h1 { font-size: 18px; color: #0F3446; margin: 0 0 8px; font-weight: bold; }
            h2 { font-size: 14px; color: #0F3446; margin: 16px 0 8px; border-bottom: 2px solid #C4A052; padding-bottom: 4px; font-weight: bold; }
            h3 { font-size: 13px; color: #0F3446; margin: 14px 0 6px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin: 8px 0; }
            th, td { border: 1px solid #D8D0BE; padding: 7px 10px; text-align: right; vertical-align: top; font-size: 11px; }
            th { background-color: #0F3446; color: #FFFFFF; font-weight: bold; }
            tr:nth-child(even) td { background-color: #F8F9FA; }
            .num, .pdf-num { direction: ltr; text-align: left; unicode-bidi: embed; font-variant-numeric: tabular-nums; }
            .meta { color: #5E6E73; font-size: 10px; }
            .zone { margin-bottom: 14px; }
            .sig-box { min-height: 40px; }
            .pdf-label { width: 35%; font-weight: bold; background-color: #F3F6F8; white-space: nowrap; }
            table.pdf-meta td.num, .pdf-meta td.num { width: 65%; text-align: right; }
            .highlight { background-color: #E6F2EE; padding: 6px 10px; border-radius: 4px; border-right: 3px solid #27A588; }
            .gold-bar { height: 3px; background: linear-gradient(90deg, #C4A052, #27A588); margin: 8px 0; }
        ';
    }

    /**
     * Company header with logo, name, tax number.
     */
    public static function header(string $title, bool $includeCr = false): string
    {
        $company = CompanyProfile::current();
        $logo = '';
        if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
            $full = Storage::disk('public')->path($company->logo_path);
            $logo = '<img src="'.e($full).'" style="height: 52px;" />';
        } elseif ($company->logo_path && is_file(public_path($company->logo_path))) {
            $logo = '<img src="'.e(public_path($company->logo_path)).'" style="height: 52px;" />';
        }

        $tax = $company->tax_number
            ? '<div class="meta">الرقم الضريبي: <span class="num">'.e((string) $company->tax_number).'</span></div>'
            : '';
        $cr = $includeCr && $company->commercial_register
            ? '<div class="meta">السجل التجاري: <span class="num">'.e((string) $company->commercial_register).'</span></div>'
            : '';
        $address = $includeCr && $company->address
            ? '<div class="meta">العنوان: '.e((string) $company->address).'</div>'
            : '';

        return '<table style="width:100%; border:none; margin-bottom:10px;"><tr>'
            .'<td style="border:none; width:70%; vertical-align:top;">'
            .'<h1>'.e($title).'</h1>'
            .'<div style="font-size:13px; color:#0F3446; font-weight:bold;">'.e((string) $company->name).'</div>'
            .$tax.$cr.$address
            .'</td>'
            .'<td style="border:none; width:30%; text-align:left; vertical-align:top;">'
            .$logo
            .'</td>'
            .'</tr></table>'
            .'<div class="gold-bar"></div>';
    }

    /**
     * Render a complete PDF from body HTML.
     * Time: O(n) | Space: O(n)
     */
    public static function render(string $title, string $bodyHtml, string $paper = 'A4', bool $includeCr = false): string
    {
        $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
            .'<style>'.self::standardCss().'</style></head><body>'
            .self::header($title, $includeCr)
            .$bodyHtml
            .'</body></html>';

        return self::outputFromHtml($html, $paper);
    }

    /**
     * Render PDF from full HTML (for views/templates that build their own HTML).
     * Time: O(n) | Space: O(n)
     */
    public static function outputFromHtml(string $html, string $paper = 'A4'): string
    {
        $tempDir = storage_path('app/mpdf-temp');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Inject standard CSS when the HTML has no stylesheet block yet.
        if (! str_contains($html, 'font-family: ibmplex') && ! str_contains($html, 'standardCss')) {
            if (stripos($html, '<head>') !== false) {
                $html = preg_replace(
                    '/<head>/i',
                    '<head><style>'.self::standardCss().'</style>',
                    $html,
                    1
                ) ?? $html;
            } elseif (stripos($html, '<html') === false) {
                $html = '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8">'
                    .'<style>'.self::standardCss().'</style></head><body>'
                    .$html
                    .'</body></html>';
            }
        }

        $mpdf = self::createMpdf($paper);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Shape text — NO-OP. mPDF handles Arabic natively.
     * Kept for backward compatibility.
     */
    public static function shape(string $text): string
    {
        return $text;
    }

    /**
     * Shape HTML — NO-OP. mPDF handles Arabic natively.
     * Kept for backward compatibility.
     */
    public static function shapeHtml(string $html): string
    {
        return $html;
    }

    /**
     * Font / body CSS for templates that inject stylesheets.
     */
    public static function fontFace(): string
    {
        return self::standardCss();
    }

    /**
     * Default font name.
     */
    public static function defaultFont(): string
    {
        return 'ibmplex';
    }

    /**
     * Path to primary Arabic TTF (IBM Plex), fallback Amiri.
     */
    public static function fontPath(): ?string
    {
        $ibm = resource_path('fonts/IBMPlexSansArabic-Regular.ttf');
        if (is_file($ibm)) {
            return $ibm;
        }
        $amiri = resource_path('fonts/Amiri-Regular.ttf');

        return is_file($amiri) ? $amiri : null;
    }

    /**
     * PDF options — NO-OP for backward compatibility.
     *
     * @return array<string, mixed>
     */
    public static function pdfOptions(): array
    {
        return [];
    }

    /**
     * Apply options — NO-OP for backward compatibility.
     */
    public static function applyOptions(mixed $pdf): mixed
    {
        return $pdf;
    }
}
