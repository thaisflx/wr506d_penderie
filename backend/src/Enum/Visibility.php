<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Visibilité d'un logement, d'une pièce ou d'un storage (privé par défaut)
enum Visibility: string implements TranslatableInterface
{
    case Private = 'private';
    case CloseFriends = 'close_friends';
    case Followers = 'followers';

    // Libellé dans la langue demandée (clé enum.visibility.<valeur> du domaine messages), pour Twig et le back office
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.visibility.'.$this->value, [], 'messages', $locale);
    }
}
