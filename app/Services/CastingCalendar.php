<?php

namespace App\Services;

use App\Models\Selection;
use Carbon\CarbonImmutable;

class CastingCalendar
{
    public const TIMEZONE = 'Europe/Rome';

    public function openingDate(): CarbonImmutable
    {
        return CarbonImmutable::parse(config('casting.project.opening_date'), self::TIMEZONE)->startOfDay();
    }

    public function permitsIntake(): bool
    {
        return CarbonImmutable::now(self::TIMEZONE)->gte($this->openingDate());
    }

    public function auditionMonth(): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', config('casting.project.audition_month'), self::TIMEZONE)
            ->locale('it')->isoFormat('MMMM YYYY');
    }

    public function status(?Selection $selection): array
    {
        $phase = $selection?->phases->first();
        $opening = $this->openingDate();
        $phaseStart = $phase?->starts_at?->toImmutable()->timezone(self::TIMEZONE);
        if ($phaseStart !== null && $phaseStart->gt($opening)) {
            $opening = $phaseStart;
        }

        $deadline = $phase?->ends_at?->toImmutable()->timezone(self::TIMEZONE);
        $now = CarbonImmutable::now(self::TIMEZONE);

        return [
            'opensAt'=>$opening,
            'deadline'=>$deadline,
            'isOpen'=>$selection?->acceptsApplications() ?? false,
            'isUpcoming'=>$selection?->is_open === true && $phase !== null
                && $now->lt($opening) && ($deadline === null || $deadline->gte($opening)),
            'auditionMonth'=>$this->auditionMonth(),
        ];
    }
}
