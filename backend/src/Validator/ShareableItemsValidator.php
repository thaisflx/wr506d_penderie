<?php

namespace App\Validator;

use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\User;
use App\Service\ItemExposure;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ShareableItemsValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ItemExposure $exposure,
        private readonly Security $security,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ShareableItems) {
            throw new UnexpectedTypeException($constraint, ShareableItems::class);
        }
        if (!is_iterable($value)) {
            return;
        }

        // À la création, la validation passe avant OotdProcessor : le propriétaire sera l'utilisateur connecté
        $ootd = $this->context->getObject();
        $owner = $ootd instanceof Ootd ? $ootd->getOwner() : null;
        $owner ??= $this->security->getUser();
        if (!$owner instanceof User) {
            $owner = null;
        }

        foreach ($value as $item) {
            if (!$item instanceof Item) {
                continue;
            }
            if (null === $owner || $item->getOwner() !== $owner) {
                $this->context->buildViolation($constraint->notMineMessage)->addViolation();
            } elseif (!$this->exposure->isShareable($item)) {
                $this->context->buildViolation($constraint->hiddenMessage)
                    ->setParameter('{{ name }}', (string) $item->getName())
                    ->addViolation();
            }
        }
    }
}
