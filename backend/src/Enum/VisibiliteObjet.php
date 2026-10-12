<?php

namespace App\Enum;

enum VisibiliteObjet: string
{
    case Prive = 'prive';
    case AmisProches = 'amis_proches';
    case TousAmis = 'tous_amis';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Prive => 'Privé',
            self::AmisProches => 'Amis proches',
            self::TousAmis => 'Tous les amis',
            self::Public => 'Public',
        };
    }
}
