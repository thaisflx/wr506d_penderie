<?php

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Item;
use App\Entity\Place;
use App\Entity\User;
use App\State\OwnerProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class OwnerProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{Place|Item}>
     */
    public static function resourceProvider(): iterable
    {
        yield 'logement' => [new Place()];
        yield 'objet' => [new Item()];
    }

    #[DataProvider('resourceProvider')]
    public function testConnectedUserBecomesOwnerOnCreation(Place|Item $resource): void
    {
        $user = new User();

        $result = $this->process($resource, $user);

        self::assertSame($user, $resource->getOwner());
        self::assertSame($resource, $result);
    }

    #[DataProvider('resourceProvider')]
    public function testExistingOwnerIsKept(Place|Item $resource): void
    {
        $owner = new User();
        $resource->setOwner($owner);

        $this->process($resource, new User());

        self::assertSame($owner, $resource->getOwner());
    }

    public function testAnonymousRequestLeavesOwnerEmpty(): void
    {
        $item = new Item();

        $this->process($item, null);

        self::assertNull($item->getOwner());
    }

    public function testNonAppUserIsNotSetAsOwner(): void
    {
        $item = new Item();

        $this->process($item, new InMemoryUser('admin', null));

        self::assertNull($item->getOwner());
    }

    private function process(Place|Item $resource, ?UserInterface $user): mixed
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $operation = new Post();
        // L'enregistrement est toujours délégué au processor Doctrine, avec les mêmes arguments
        $persist = $this->createMock(ProcessorInterface::class);
        $persist->expects(self::once())->method('process')
            ->with($resource, $operation, ['id' => 1], ['ctx' => true])
            ->willReturnArgument(0);

        return (new OwnerProcessor($persist, $security))->process($resource, $operation, ['id' => 1], ['ctx' => true]);
    }
}
