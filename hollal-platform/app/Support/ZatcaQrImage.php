<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\File;

/**
 * Render ZATCA TLV payload as a Dompdf-friendly SVG file.
 * Time: O(n) payload | Space: O(qr modules)
 */
final class ZatcaQrImage
{
    /** Absolute filesystem path to a temporary SVG (caller may leave it for Dompdf). */
    public static function svgFile(string $payload, int $size = 160): string
    {
        $svg = (new SvgWriter)->write(
            new QrCode(data: $payload, size: $size, margin: 4)
        )->getString();

        $dir = storage_path('app/tmp/qr');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/zatca-'.sha1($payload).'.svg';
        File::put($path, $svg);

        return $path;
    }

    public static function imgTag(string $payload, int $size = 160): string
    {
        if ($payload === '') {
            return '';
        }

        $path = str_replace('\\', '/', self::svgFile($payload, $size));

        return '<img src="file://'.$path.'" width="'.$size.'" height="'.$size.'" alt="ZATCA QR">'
            .'<p style="font-size:9px; color:#555; text-align:right; margin-top:6px;">'
            .'رمز الفاتورة الإلكتروني وفق متطلبات هيئة الزكاة والضريبة والجمارك (المرحلة أ).'
            .'<br>امسح الرمز بتطبيق «فاتورة» للتحقق من بيانات البائع والضريبة والإجمالي.'
            .'</p>';
    }
}
