<?php

namespace App\Enum;

// Tant que la demande est en attente, le demandeur n'a aucun droit.
// Le blocage n'est plus un statut : voir l'entité Block (docs/decisions.md)
enum RelationshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Accepted => 'Acceptée',
        };
    }
}
