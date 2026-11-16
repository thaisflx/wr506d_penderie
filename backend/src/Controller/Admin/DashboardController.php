<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back office du commanditaire (docs/decisions.md, « Back office ») : réservé à ROLE_ADMIN (security.yaml),
 * textes dans le domaine de traduction « admin », langue du compte de l'admin.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Lendly')
            ->setTranslationDomain('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('menu.dashboard', 'fa fa-chart-simple');
        yield MenuItem::section('menu.community');
        yield MenuItem::linkTo(UserCrudController::class, 'menu.users', 'fa fa-user');
        yield MenuItem::linkTo(OotdCrudController::class, 'menu.looks', 'fa fa-shirt');
        yield MenuItem::section('menu.content');
        yield MenuItem::linkTo(ItemCrudController::class, 'menu.items', 'fa fa-box');
        yield MenuItem::linkTo(CategoryCrudController::class, 'menu.categories', 'fa fa-tags');
    }
}
