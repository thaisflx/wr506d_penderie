<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum CategoryType: string implements TranslatableInterface
{
    case Top = 'top';
    case Bottom = 'bottom';
    case Shoes = 'shoes';
    case Media = 'media';
    case Other = 'other';

    // Libellé dans la langue demandée (clé enum.category_type.<valeur> du domaine messages), pour Twig et le back office
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.category_type.'.$this->value, [], 'messages', $locale);
    }
}
