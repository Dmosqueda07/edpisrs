<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class WorkingDayCalculator
{
    public function subtractWorkingDays(int $days, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $date = $from ?? CarbonImmutable::now();
        $remaining = $days;

        while ($remaining > 0) {
            $date = $date->subDay();

            if ($date->isWeekday()) {
                $remaining--;
            }
        }

        return $date;
    }
}
