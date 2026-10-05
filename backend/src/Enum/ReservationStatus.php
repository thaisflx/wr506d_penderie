<?php

namespace App\Enum;

enum ReservationStatus: string
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Late = 'late';

    public function label(): string
    {
        return match ($this) {
            self::Ongoing => 'En cours',
            self::Completed => 'Terminé',
            self::Late => 'En retard',
        };
    }
}
