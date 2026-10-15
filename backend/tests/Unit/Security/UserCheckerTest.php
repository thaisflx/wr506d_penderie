<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\UserChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class UserCheckerTest extends TestCase
{
    private UserChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new UserChecker();
    }

    public function testSuspendedUserCannotLogIn(): void
    {
        $user = (new User())->setIsSuspended(true);

        try {
            $this->checker->checkPreAuth($user);
            self::fail('Un compte suspendu doit être refusé');
        } catch (CustomUserMessageAccountStatusException $e) {
            // Ce message est renvoyé tel quel au front
            self::assertSame('Ce compte est suspendu.', $e->getMessageKey());
        }
    }

    public function testActiveUserCanLogIn(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPreAuth(new User());
        $this->checker->checkPostAuth(new User());
    }

    public function testOtherUserClassesAreIgnored(): void
    {
        $this->expectNotToPerformAssertions();

        $this->checker->checkPreAuth(new InMemoryUser('admin', null));
    }
}
