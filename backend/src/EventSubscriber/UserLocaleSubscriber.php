<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Langue de la requête pour une personne connectée : celle de son compte (User.locale), qui l'emporte sur
 * l'en-tête Accept-Language (docs/decisions.md, « Internationalisation »).
 *
 * Avant ce subscriber, Symfony a déjà choisi la langue d'après Accept-Language (set_locale_from_accept_language,
 * parmi fr, en, de ; fr par défaut) : c'est celle des visiteurs anonymes et de l'écran de connexion.
 */
final class UserLocaleSubscriber implements EventSubscriberInterface
{
    // Juste après le firewall (priorité 8), qui authentifie le jeton JWT ; avant la lecture et la validation par API Platform
    public const PRIORITY = 7;

    public function __construct(
        private readonly Security $security,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', self::PRIORITY]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$event->isMainRequest() || !$user instanceof User) {
            return;
        }

        // Une valeur inattendue en base ne doit jamais faire sortir des langues gérées
        $locale = \in_array($user->getLocale(), User::LOCALES, true) ? $user->getLocale() : User::DEFAULT_LOCALE;
        $event->getRequest()->setLocale($locale);
        // Traducteur, validateur et autres services qui dépendent de la langue
        $this->localeSwitcher->setLocale($locale);
    }
}
