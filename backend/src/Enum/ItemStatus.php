<?php

namespace App\Enum;

enum ItemStatus: string
{
    case Available = 'available';
    case InLaundry = 'in_laundry';
    case Reserved = 'reserved';
    case Lent = 'lent';
    case ForSale = 'for_sale';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::InLaundry => 'Au sale',
            self::Reserved => 'Réservé',
            self::Lent => 'Prêté',
            self::ForSale => 'À la vente',
        };
    }
}
