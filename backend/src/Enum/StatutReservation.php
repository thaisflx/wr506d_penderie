<?php

namespace App\Enum;

enum StatutReservation: string
{
    case EnCours = 'en_cours';
    case Termine = 'termine';
    case EnRetard = 'en_retard';

    public function label(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
            self::EnRetard => 'En retard',
        };
    }
}
