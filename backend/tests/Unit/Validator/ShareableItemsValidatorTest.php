<?php

namespace App\Tests\Unit\Validator;

use App\Entity\Category;
use App\Entity\Item;
use App\Entity\Ootd;
use App\Entity\Place;
use App\Entity\Room;
use App\Entity\Storage;
use App\Entity\User;
use App\Enum\Visibility;
use App\Service\ItemExposure;
use App\Validator\ShareableItems;
use App\Validator\ShareableItemsValidator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * Cas que l'API n'atteint pas (un objet d'une autre personne est déjà introuvable : 400),
 * testés directement sur le validateur.
 *
 * @extends ConstraintValidatorTestCase<ShareableItemsValidator>
 */
final class ShareableItemsValidatorTest extends ConstraintValidatorTestCase
{
    private ?User $connected = null;

    protected function createValidator(): ShareableItemsValidator
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturnCallback(fn () => $this->connected);

        return new ShareableItemsValidator(new ItemExposure(), $security);
    }

    public function testMyShareableItemsAreValid(): void
    {
        $camille = new User();
        $this->setObject((new Ootd())->setOwner($camille));

        $this->validator->validate([$this->item($camille)], new ShareableItems());

        $this->assertNoViolation();
    }

    public function testAnItemOfSomeoneElseIsRefused(): void
    {
        $this->setObject((new Ootd())->setOwner(new User()));

        $this->validator->validate([$this->item(new User())], new ShareableItems());

        $this->buildViolation('Tu ne peux ajouter que tes propres objets.')->assertRaised();
    }

    public function testANewOotdBelongsToTheConnectedUser(): void
    {
        // À la création, le propriétaire n'est posé qu'après la validation (OotdProcessor)
        $this->connected = new User();
        $this->setObject(new Ootd());

        $this->validator->validate([$this->item($this->connected)], new ShareableItems());

        $this->assertNoViolation();
    }

    public function testWithoutOwnerNorConnectedUserEveryItemIsRefused(): void
    {
        $this->setObject(new Ootd());

        $this->validator->validate([$this->item(new User())], new ShareableItems());

        $this->buildViolation('Tu ne peux ajouter que tes propres objets.')->assertRaised();
    }

    public function testValuesThatAreNotItemsAreIgnored(): void
    {
        $this->validator->validate(null, new ShareableItems());
        $this->validator->validate(['pas un objet'], new ShareableItems());

        $this->assertNoViolation();
    }

    public function testAnotherConstraintIsRejected(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate([], new NotNull());
    }

    private function item(User $owner): Item
    {
        $room = (new Room())->setVisibility(Visibility::Followers)->setPlace((new Place())->setVisibility(Visibility::Followers));
        $storage = (new Storage())->setVisibility(Visibility::Followers)->setRoom($room);

        return (new Item())->setName('T-shirt')->setOwner($owner)->setCategory((new Category())->setStorage($storage));
    }
}
