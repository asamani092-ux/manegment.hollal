<?php

namespace App\Support;

/**
 * RFC 5987 Content-Disposition: ASCII fallback + UTF-8 filename*.
 * Time: O(n) in filename length | Space: O(n).
 */
final class DownloadHeaders
{
    public static function contentDisposition(string $filename, string $disposition = 'attachment'): string
    {
        $safe = str_replace(['"', '\\', "\r", "\n"], '', $filename);
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $safe) ?: 'download';

        return $disposition.'; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($safe);
    }

    /** @return array<string, string> */
    public static function pdf(string $filename): array
    {
        return [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => self::contentDisposition($filename),
        ];
    }
}
