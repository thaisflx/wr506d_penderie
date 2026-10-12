<?php

namespace App\Enum;

enum CategoryType: string
{
    case Top = 'top';
    case Bottom = 'bottom';
    case Shoes = 'shoes';
    case Media = 'media';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Top => 'Haut',
            self::Bottom => 'Bas',
            self::Shoes => 'Chaussures',
            self::Media => 'Média',
            self::Other => 'Autre',
        };
    }
}
