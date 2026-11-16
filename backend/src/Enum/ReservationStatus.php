<?php

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ReservationStatus: string implements TranslatableInterface
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Late = 'late';

    // Libellé dans la langue demandée (clé enum.reservation_status.<valeur> du domaine messages), pour Twig et le back office
    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.reservation_status.'.$this->value, [], 'messages', $locale);
    }
}
