<?php

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\UserLocaleSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Langue d'une personne connectée : celle de son compte, qui l'emporte sur Accept-Language (docs/decisions.md, i18n).
 */
final class UserLocaleSubscriberTest extends TestCase
{
    private LocaleSwitcher $switcher;
    // Service sensible à la langue (comme le traducteur) : le subscriber doit le mettre à jour
    private LocaleAwareInterface $translator;

    protected function setUp(): void
    {
        $this->translator = new class implements LocaleAwareInterface {
            private string $locale = 'fr';

            public function setLocale(string $locale): void
            {
                $this->locale = $locale;
            }

            public function getLocale(): string
            {
                return $this->locale;
            }
        };
        $this->switcher = new LocaleSwitcher('fr', [$this->translator]);
    }

    public function testRunsJustAfterTheFirewall(): void
    {
        // Le firewall (priorité 8) authentifie le jeton ; la lecture et la validation par API Platform viennent après
        self::assertSame([KernelEvents::REQUEST => ['onKernelRequest', 7]], UserLocaleSubscriber::getSubscribedEvents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localeProvider(): iterable
    {
        yield 'allemand' => ['de', 'de'];
        yield 'anglais' => ['en', 'en'];
        yield 'français' => ['fr', 'fr'];
        yield 'valeur inattendue en base : français' => ['xx', 'fr'];
    }

    #[DataProvider('localeProvider')]
    public function testTheLanguageOfTheAccountWinsOverTheHeader(string $accountLocale, string $expected): void
    {
        $request = $this->request('en');

        $this->subscriber((new User())->setLocale($accountLocale))->onKernelRequest($this->event($request));

        self::assertSame($expected, $request->getLocale());
        self::assertSame($expected, $this->translator->getLocale());
    }

    public function testAnonymousVisitorKeepsTheLanguageOfTheHeader(): void
    {
        $request = $this->request('de');

        $this->subscriber(null)->onKernelRequest($this->event($request));

        self::assertSame('de', $request->getLocale());
        self::assertSame('fr', $this->translator->getLocale());
    }

    public function testSubRequestsAreIgnored(): void
    {
        $request = $this->request('en');

        $this->subscriber((new User())->setLocale('de'))->onKernelRequest($this->event($request, HttpKernelInterface::SUB_REQUEST));

        self::assertSame('en', $request->getLocale());
    }

    private function subscriber(?User $user): UserLocaleSubscriber
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return new UserLocaleSubscriber($security, $this->switcher);
    }

    // Requête dont la langue a déjà été tirée d'Accept-Language par Symfony
    private function request(string $headerLocale): Request
    {
        $request = Request::create('/api/me');
        $request->setLocale($headerLocale);

        return $request;
    }

    private function event(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }
}
