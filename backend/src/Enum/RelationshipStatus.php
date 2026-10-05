<?php

namespace App\Enum;

// Tant que la demande est en attente, le demandeur n'a aucun droit
enum RelationshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
            self::Blocked => 'Bloquée',
        };
    }
}
