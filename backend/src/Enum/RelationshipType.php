<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum RelationshipType: string implements TranslatableInterface
{
    case Follower = 'follower';
    case CloseFriend = 'close_friend';

    // Libellé dans la langue demandée (clé enum.relationship_type.<valeur> du domaine messages), pour Twig et le back office
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.relationship_type.'.$this->value, [], 'messages', $locale);
    }
}
