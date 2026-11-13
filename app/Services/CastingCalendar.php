<?php

namespace App\Services;

use App\Models\Selection;
use App\Models\SelectionPhase;
use Carbon\CarbonImmutable;

class CastingCalendar
{
    public const TIMEZONE = 'Europe/Rome';

    public function openingDate(?SelectionPhase $phase = null): CarbonImmutable
    {
        return $phase?->starts_at?->toImmutable()->timezone(self::TIMEZONE)
            ?? CarbonImmutable::parse(config('casting.project.opening_date'), self::TIMEZONE)->startOfDay();
    }

    public function auditionMonth(): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', config('casting.project.audition_month'), self::TIMEZONE)
            ->locale('it')->isoFormat('MMMM YYYY');
    }

    public function status(?Selection $selection): array
    {
        $phase = $selection?->phases->first();
        $opening = $this->openingDate($phase);
        $deadline = $phase?->ends_at?->toImmutable()->timezone(self::TIMEZONE);
        $now = CarbonImmutable::now(self::TIMEZONE);
        $enabled = $selection?->is_open === true && $phase !== null
            && ($deadline === null || $deadline->gte($opening));

        return [
            'opensAt'=>$opening,
            'deadline'=>$deadline,
            'isOpen'=>$enabled && $now->gte($opening) && ($deadline === null || $now->lte($deadline)),
            'isUpcoming'=>$enabled && $now->lt($opening),
        ];
    }
}
