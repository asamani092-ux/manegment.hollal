<?php

namespace App\Support;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\File;

/**
 * ZATCA QR as SVG file for Dompdf (HTML-table QR exhausts memory).
 * Time: O(n) payload | Space: O(qr modules)
 */
final class ZatcaQrImage
{
    public static function svgFile(string $payload, int $size = 120): string
    {
        $svg = (new SvgWriter)->write(
            new QrCode(data: $payload, size: $size, margin: 2)
        )->getString();

        $dir = storage_path('app/tmp/qr');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/zatca-'.sha1($payload.$size).'.svg';
        File::put($path, $svg);

        return $path;
    }

    public static function imgTag(string $payload, int $size = 120): string
    {
        if ($payload === '') {
            return '<p class="pdf-num">لا يوجد رمز إلكتروني لهذه الفاتورة.</p>';
        }

        $path = str_replace('\\', '/', self::svgFile($payload, $size));

        return '<div style="text-align:right;">'
            .'<img src="file://'.$path.'" width="'.$size.'" height="'.$size.'" alt="ZATCA QR" style="display:block; margin:4px 0 4px auto;">'
            .'<p style="font-size:9px; color:#555; margin:4px 0 0; text-align:right; max-width:280px; margin-right:0; margin-left:auto;">'
            .'رمز الفاتورة الإلكتروني — هيئة الزكاة والضريبة والجمارك (المرحلة أ).'
            .'<br>امسحه بتطبيق «فاتورة» للتحقق.'
            .'</p>'
            .'</div>';
    }
}
