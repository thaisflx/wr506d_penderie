<?php

namespace App\Enum;

enum ReservationType: string
{
    case Sale = 'sale';
    case Loan = 'loan';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Vente',
            self::Loan => 'Prêt',
        };
    }
}
