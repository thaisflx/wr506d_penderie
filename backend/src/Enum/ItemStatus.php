<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ItemStatus: string implements TranslatableInterface
{
    case Available = 'available';
    case InLaundry = 'in_laundry';
    case Reserved = 'reserved';
    case Lent = 'lent';
    case ForSale = 'for_sale';

    // Libellé dans la langue demandée (clé enum.item_status.<valeur> du domaine messages), pour Twig et le back office
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.item_status.'.$this->value, [], 'messages', $locale);
    }
}
