<?php

namespace Tests\Unit;

use App\Support\PdfArabic;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PdfArabicShapeTest extends TestCase
{
    #[Test]
    public function preserves_western_digits_and_vat_numbers_in_mixed_lines(): void
    {
        $vat = '300123456700003';
        $out = PdfArabic::shape('الرقم الضريبي: '.$vat);

        $this->assertStringContainsString($vat, $out);
        $this->assertStringNotContainsString('٣', $out);
        // Must not reverse the VAT id.
        $this->assertStringNotContainsString(strrev($vat), $out);
    }

    #[Test]
    public function preserves_decimal_amounts(): void
    {
        $out = PdfArabic::shape('المجموع: 1,234.56');

        $this->assertStringContainsString('1,234.56', $out);
        $this->assertStringNotContainsString('56.234,1', $out);
        $this->assertStringNotContainsString('56.1234', $out);
    }

    #[Test]
    public function preserves_invoice_numbers_next_to_arabic_title(): void
    {
        $out = PdfArabic::shape('فاتورة ضريبية — INV-2026-000008');

        $this->assertStringContainsString('INV-2026-000008', $out);
    }

    #[Test]
    public function shape_html_keeps_numeric_table_cells_intact(): void
    {
        $html = '<table><tr><td>خدمة استشارية</td><td class="num">1,250.00</td></tr></table>';
        $out = PdfArabic::shapeHtml($html);

        $this->assertStringContainsString('1,250.00', $out);
        $this->assertStringContainsString('class="num"', $out);
    }
}
