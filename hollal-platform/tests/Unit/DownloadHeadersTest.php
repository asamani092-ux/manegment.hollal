<?php

namespace Tests\Unit;

use App\Support\DownloadHeaders;
use PHPUnit\Framework\TestCase;

class DownloadHeadersTest extends TestCase
{
    public function test_content_disposition_includes_utf8_filename_star(): void
    {
        $header = DownloadHeaders::contentDisposition('محضر اجتماع.pdf');

        $this->assertStringContainsString('attachment; filename="', $header);
        $this->assertStringContainsString("filename*=UTF-8''", $header);
        $this->assertStringContainsString(rawurlencode('محضر اجتماع.pdf'), $header);
    }
}
