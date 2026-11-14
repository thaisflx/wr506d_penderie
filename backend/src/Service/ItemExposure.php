<?php

namespace App\Service;

use App\Entity\Item;
use App\Enum\Visibility;

/**
 * Un objet peut-il être montré dans un look du jour (OOTD) ? Voir docs/decisions.md, lot 2.
 * Non s'il est perso, ou si son rangement, sa pièce ou son logement est privé ou perso :
 * l'utilisateur l'a volontairement caché.
 */
final class ItemExposure
{
    public function isShareable(Item $item): bool
    {
        if ($item->isPersonal()) {
            return false;
        }

        $storage = $item->getStorage();
        $room = $storage?->getRoom();
        $place = $room?->getPlace();
        if (null === $storage || null === $room || null === $place) {
            return false;
        }

        return !$storage->isPersonal() && Visibility::Private !== $storage->getVisibility()
            && !$room->isPersonal() && Visibility::Private !== $room->getVisibility()
            && Visibility::Private !== $place->getVisibility();
    }
}
