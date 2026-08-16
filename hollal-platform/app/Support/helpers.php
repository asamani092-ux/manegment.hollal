<?php

use App\Support\HollalTime;
use Carbon\CarbonInterface;

if (! function_exists('hollal_dt')) {
    /** Display datetime as 12-hour Arabic (ص/م). */
    function hollal_dt(?CarbonInterface $dt, bool $withSeconds = false): string
    {
        return HollalTime::datetime($dt, $withSeconds);
    }
}

if (! function_exists('hollal_time')) {
    function hollal_time(?CarbonInterface $dt): string
    {
        return HollalTime::time($dt);
    }
}
