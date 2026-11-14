<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Objets d'un look du jour : seulement les miens, et jamais un objet privé ou perso (App\Service\ItemExposure).
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ShareableItems extends Constraint
{
    public string $notMineMessage = 'Tu ne peux ajouter que tes propres objets.';
    public string $hiddenMessage = '« {{ name }} » est privé ou perso : il ne peut pas apparaître dans un look.';
}
