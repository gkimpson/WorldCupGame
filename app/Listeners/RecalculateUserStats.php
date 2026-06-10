<?php

namespace App\Listeners;

use App\Events\ResultImported;

class RecalculateUserStats
{
    public function handle(ResultImported $event): void {}
}
