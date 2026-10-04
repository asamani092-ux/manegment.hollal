<?php

namespace App\Events;

use App\Models\Violation;
use Illuminate\Foundation\Events\Dispatchable;

class ViolationApplied
{
    use Dispatchable;

    public function __construct(public Violation $violation) {}
}
