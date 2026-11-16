<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Objets d'un look du jour : seulement les miens, et jamais un objet privé ou perso (App\Service\ItemExposure).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ShareableItems extends Constraint
{
    public string $notMineMessage = 'ootd.item_not_mine';
    public string $hiddenMessage = 'ootd.item_hidden';
}
