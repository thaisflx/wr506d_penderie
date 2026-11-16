<?php

namespace App\Controller\Admin;

use App\Enum\CategoryType;
use App\Service\AdminStats;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Back office du commanditaire (docs/decisions.md, « Back office ») : réservé à ROLE_ADMIN (security.yaml),
 * textes dans le domaine de traduction « admin », langue du compte de l'admin.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminStats $stats,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Les 8 KPI (docs/decisions.md), nombres et dates formatés dans la langue de l'admin
    public function index(): Response
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
        $stats = $this->stats->all();
        $decimal = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
        $week = new \IntlDateFormatter($locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'd MMM');
        $weeks = static fn (array $rows) => array_map(static fn (array $row) => ['label' => $week->format($row['start']), 'count' => $row['count']], $rows);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'signupWeeks' => $weeks($stats['signups']['weeks']),
            'lookWeeks' => $weeks($stats['looks']['weeks']),
            'median' => $decimal->format($stats['itemsPerUser']['median']),
            'average' => $decimal->format($stats['itemsPerUser']['average']),
            'itemsByType' => array_map(
                fn (string $type, int $count) => ['label' => CategoryType::from($type)->trans($this->translator), 'count' => $count],
                array_keys($stats['itemsByType']),
                $stats['itemsByType'],
            ),
        ]);
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
