<?php

namespace App\Enum;

enum TypeCategorie: string
{
    case Haut = 'haut';
    case Bas = 'bas';
    case Chaussures = 'chaussures';
    case Media = 'media';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::Haut => 'Haut',
            self::Bas => 'Bas',
            self::Chaussures => 'Chaussures',
            self::Media => 'Média',
            self::Autre => 'Autre',
        };
    }
}
