<?php

namespace App\Events;

use App\Models\Fixture;
use Illuminate\Foundation\Events\Dispatchable;

class ResultImported
{
    use Dispatchable;

    public function __construct(public readonly Fixture $fixture) {}
}
