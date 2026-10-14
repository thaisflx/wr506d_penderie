<?php

namespace App\Enum;

// Visibilité d'un logement, d'une pièce ou d'un storage (privé par défaut)
enum Visibility: string
{
    case Private = 'private';
    case CloseFriends = 'close_friends';
    case Followers = 'followers';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privé',
            self::CloseFriends => 'Amis proches',
            self::Followers => 'Abonnés',
        };
    }
}
