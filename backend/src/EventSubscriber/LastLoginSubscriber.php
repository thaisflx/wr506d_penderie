<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Date de dernière connexion (User.lastLoginAt), pour le KPI des personnes actives du back office
 * (docs/decisions.md, « Back office »).
 *
 * Seulement quand la personne saisit ses identifiants : /api/login_check (firewall « login ») ou le formulaire du
 * back office (firewall « admin »). L'authentificateur JWT déclenche lui aussi LoginSuccessEvent à chaque requête
 * de l'API : sans ce filtre, chaque appel écrirait en base.
 */
final class LastLoginSubscriber implements EventSubscriberInterface
{
    public const FIREWALLS = ['login', 'admin'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || !\in_array($event->getFirewallName(), self::FIREWALLS, true)) {
            return;
        }

        $user->setLastLoginAt($this->clock->now());
        $this->entityManager->flush();
    }
}
