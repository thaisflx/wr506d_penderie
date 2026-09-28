<?php

namespace App\Enum;

enum StatutObjet: string
{
    case Disponible = 'disponible';
    case AuSale = 'au_sale';
    case Reserve = 'reserve';
    case Prete = 'prete';
    case ALaVente = 'a_la_vente';

    public function label(): string
    {
        return match ($this) {
            self::Disponible => 'Disponible',
            self::AuSale => 'Au salé',
            self::Reserve => 'Réservé',
            self::Prete => 'Prêté',
            self::ALaVente => 'À la vente',
        };
    }
}
