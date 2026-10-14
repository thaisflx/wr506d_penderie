<?php

namespace App\Enum;

enum RelationshipType: string
{
    case Follower = 'follower';
    case CloseFriend = 'close_friend';

    public function label(): string
    {
        return match ($this) {
            self::Follower => 'Abonné',
            self::CloseFriend => 'Ami proche',
        };
    }
}
