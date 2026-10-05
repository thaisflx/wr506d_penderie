<?php

namespace App\Enum;

enum Visibility: string
{
    case Private = 'private';
    case CloseFriends = 'close_friends';
    case AllFriends = 'all_friends';
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privé',
            self::CloseFriends => 'Amis proches',
            self::AllFriends => 'Tous les amis',
            self::Public => 'Public',
        };
    }
}
