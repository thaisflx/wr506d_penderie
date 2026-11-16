<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Connexion au back office (firewall « admin », avec session) : formulaire d'EasyAdmin protégé par un jeton CSRF.
 * Le firewall traite l'envoi du formulaire ; la route de déconnexion (/admin/logout) est créée par Symfony.
 */
final class SecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('@EasyAdmin/page/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'translation_domain' => 'admin',
            'page_title' => 'Lendly',
            'csrf_token_intention' => 'authenticate',
            // Hors du dashboard, le modèle d'EasyAdmin renverrait vers « / » après la connexion
            'target_path' => $this->generateUrl('admin'),
            'username_label' => 'login.email',
            'password_label' => 'login.password',
            'sign_in_label' => 'login.sign_in',
        ]);
    }
}
