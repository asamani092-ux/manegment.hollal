<?php

namespace Tests\Unit;

use App\Support\HollalTime;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HollalTimeTest extends TestCase
{
    #[Test]
    public function formats_afternoon_with_arabic_pm(): void
    {
        $dt = Carbon::parse('2026-08-16 14:30:00', 'Asia/Riyadh');

        $this->assertSame('2026-08-16 2:30 م', HollalTime::datetime($dt));
        $this->assertSame('2:30 م', HollalTime::time($dt));
    }

    #[Test]
    public function formats_morning_with_arabic_am(): void
    {
        $dt = Carbon::parse('2026-08-16 09:05:00', 'Asia/Riyadh');

        $this->assertSame('2026-08-16 9:05 ص', HollalTime::datetime($dt));
        $this->assertSame('9:05 ص', HollalTime::time($dt));
    }

    #[Test]
    public function null_returns_dash(): void
    {
        $this->assertSame('—', HollalTime::datetime(null));
        $this->assertSame('—', HollalTime::time(null));
    }

    #[Test]
    public function helpers_match_support_class(): void
    {
        $dt = Carbon::parse('2026-08-16 00:00:00', 'Asia/Riyadh');

        $this->assertSame(HollalTime::datetime($dt), hollal_dt($dt));
        $this->assertSame(HollalTime::time($dt), hollal_time($dt));
    }
}
