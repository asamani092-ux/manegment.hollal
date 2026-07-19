<?php

namespace App\Support;

/**
 * 04-B7 — ZATCA-oriented TLV QR payload (Phase A).
 *
 * Tags: 1 seller name · 2 seller VAT number · 3 timestamp (ISO-8601) ·
 * 4 invoice total (incl. VAT) · 5 VAT total. Encoded tag-length-value, base64.
 */
class TlvQr
{
    public const TAG_SELLER_NAME = 1;

    public const TAG_SELLER_VAT = 2;

    public const TAG_TIMESTAMP = 3;

    public const TAG_TOTAL = 4;

    public const TAG_VAT_TOTAL = 5;

    /** @var list<int> tags every Phase A payload must carry */
    public const REQUIRED_TAGS = [
        self::TAG_SELLER_NAME,
        self::TAG_SELLER_VAT,
        self::TAG_TIMESTAMP,
        self::TAG_TOTAL,
        self::TAG_VAT_TOTAL,
    ];

    public static function encode(
        string $sellerName,
        string $sellerVatNumber,
        string $timestamp,
        string $total,
        string $vatTotal,
    ): string {
        $payload = self::tlv(self::TAG_SELLER_NAME, $sellerName)
            .self::tlv(self::TAG_SELLER_VAT, $sellerVatNumber)
            .self::tlv(self::TAG_TIMESTAMP, $timestamp)
            .self::tlv(self::TAG_TOTAL, $total)
            .self::tlv(self::TAG_VAT_TOTAL, $vatTotal);

        return base64_encode($payload);
    }

    /**
     * Decode a base64 TLV payload back into a tag => value map.
     *
     * @return array<int, string>
     */
    public static function decode(string $base64): array
    {
        $binary = base64_decode($base64, true);

        if ($binary === false) {
            return [];
        }

        $out = [];
        $offset = 0;
        $length = strlen($binary);

        while ($offset + 2 <= $length) {
            $tag = ord($binary[$offset]);
            $valueLength = ord($binary[$offset + 1]);
            $value = substr($binary, $offset + 2, $valueLength);

            if (strlen($value) !== $valueLength) {
                break;
            }

            $out[$tag] = $value;
            $offset += 2 + $valueLength;
        }

        return $out;
    }

    /** @param array<int, string> $decoded */
    public static function hasRequiredTags(array $decoded): bool
    {
        foreach (self::REQUIRED_TAGS as $tag) {
            if (! isset($decoded[$tag]) || $decoded[$tag] === '') {
                return false;
            }
        }

        return true;
    }

    private static function tlv(int $tag, string $value): string
    {
        return chr($tag).chr(strlen($value)).$value;
    }
}
