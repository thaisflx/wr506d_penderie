<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\LastLoginSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Dernière connexion : mise à jour quand la personne saisit ses identifiants (API ou back office), jamais à chaque
 * requête authentifiée par le jeton JWT (docs/decisions.md, « Back office »).
 */
final class LastLoginSubscriberTest extends TestCase
{
    private const NOW = '2026-11-16 08:30:00';

    /**
     * @return iterable<string, array{string}>
     */
    public static function loginFirewallProvider(): iterable
    {
        yield 'connexion à l\'API (/api/login_check)' => ['login'];
        yield 'connexion au back office' => ['admin'];
    }

    #[DataProvider('loginFirewallProvider')]
    public function testLoginWithCredentialsRecordsTheDate(string $firewall): void
    {
        $user = new User();
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        (new LastLoginSubscriber($entityManager, new MockClock(self::NOW)))->onLoginSuccess($this->event($user, $firewall));

        self::assertEquals(new \DateTimeImmutable(self::NOW), $user->getLastLoginAt());
    }

    public function testEachApiRequestWithAJwtTokenChangesNothing(): void
    {
        $user = (new User())->setLastLoginAt(new \DateTimeImmutable('2026-11-01'));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        (new LastLoginSubscriber($entityManager, new MockClock(self::NOW)))->onLoginSuccess($this->event($user, 'api'));

        self::assertEquals(new \DateTimeImmutable('2026-11-01'), $user->getLastLoginAt());
    }

    public function testAnotherKindOfUserIsIgnored(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');

        (new LastLoginSubscriber($entityManager, new MockClock(self::NOW)))->onLoginSuccess($this->event(new InMemoryUser('x', null), 'login'));
    }

    public function testListensToSuccessfulLogins(): void
    {
        self::assertSame([LoginSuccessEvent::class => 'onLoginSuccess'], LastLoginSubscriber::getSubscribedEvents());
    }

    private function event(UserInterface $user, string $firewall): LoginSuccessEvent
    {
        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('camille', static fn () => $user)),
            $this->createStub(TokenInterface::class),
            new Request(),
            null,
            $firewall,
        );
    }
}
