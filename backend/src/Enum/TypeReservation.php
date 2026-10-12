<?php

namespace App\Enum;

enum TypeReservation: string
{
    case Vente = 'vente';
    case Pret = 'pret';

    public function label(): string
    {
        return match ($this) {
            self::Vente => 'Vente',
            self::Pret => 'Prêt',
        };
    }
}
