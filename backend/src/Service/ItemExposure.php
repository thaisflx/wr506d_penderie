<?php

namespace App\Service;

use App\Entity\Item;

/**
 * Un objet peut-il être montré dans un look du jour (OOTD) ? Voir docs/decisions.md, lot 2.
 * Non s'il est perso, ou si son rangement, sa pièce ou son logement est privé ou perso :
 * l'utilisateur l'a volontairement caché.
 */
final class ItemExposure
{
    // La règle est portée par l'objet (Item::isShareable, aussi exposée au front) ; ce service la rend injectable
    public function isShareable(Item $item): bool
    {
        return $item->isShareable();
    }
}
