<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Tant que la demande est en attente, le demandeur n'a aucun droit.
// Le blocage n'est plus un statut : voir l'entité Block (docs/decisions.md)
enum RelationshipStatus: string implements TranslatableInterface
{
    case Pending = 'pending';
    case Accepted = 'accepted';

    // Libellé dans la langue demandée (clé enum.relationship_status.<valeur> du domaine messages), pour Twig et le back office
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.relationship_status.'.$this->value, [], 'messages', $locale);
    }
}
