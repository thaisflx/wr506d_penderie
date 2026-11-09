<?php

namespace App\Tests\Integration\Doctrine;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use App\Doctrine\PublicUserExtension;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Cas que l'API ne peut pas atteindre (le pare-feu exige un jeton) : sans utilisateur connecté,
 * l'extension ne renvoie aucun compte, ni en recherche ni en lecture d'un profil.
 */
final class PublicUserExtensionTest extends KernelTestCase
{
    public function testAnonymousFindsNobody(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $extension = self::getContainer()->get(PublicUserExtension::class);

        $collection = $em->getRepository(User::class)->createQueryBuilder('u');
        $extension->applyToCollection($collection, new QueryNameGenerator(), User::class, null, ['filters' => ['username' => 'cam']]);
        $item = $em->getRepository(User::class)->createQueryBuilder('u')->andWhere("u.username = 'camille'");
        $extension->applyToItem($item, new QueryNameGenerator(), User::class, []);

        self::assertSame([], $collection->getQuery()->getResult());
        self::assertSame([], $item->getQuery()->getResult());
    }
}
