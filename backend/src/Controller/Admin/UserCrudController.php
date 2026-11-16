<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Comptes (docs/decisions.md, « Back office ») : l'admin voit de quoi gérer un compte (pseudo, email, rôle,
 * suspension, dates, mineur ou non, nombre d'objets), jamais la date de naissance ni le contenu du dressing.
 * Il peut seulement donner ou retirer le rôle admin et suspendre ; pas de création, pas de suppression.
 *
 * @extends AbstractCrudController<User>
 */
final class UserCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('user.singular')
            ->setEntityLabelInPlural('user.plural')
            ->setSearchFields(['username', 'email'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('username', 'user.username')->hideOnForm();
        yield EmailField::new('email', 'user.email')->hideOnForm();
        yield BooleanField::new('admin', 'user.admin')->renderAsSwitch(false);
        yield BooleanField::new('isSuspended', 'user.suspended')->renderAsSwitch(false);
        yield BooleanField::new('minor', 'user.minor')->hideOnForm();
        yield IntegerField::new('items', 'user.item_count')->hideOnForm()
            ->formatValue(static fn (mixed $value, User $user) => $user->getItems()->count());
        yield DateTimeField::new('createdAt', 'user.created_at')->hideOnForm();
        yield DateTimeField::new('lastLoginAt', 'user.last_login_at')->hideOnForm();
    }

    /**
     * Un admin ne peut ni se suspendre ni se retirer le rôle admin : il bloquerait l'administration.
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance === $this->getUser() && (!$entityInstance->isAdmin() || $entityInstance->isSuspended())) {
            $entityManager->refresh($entityInstance);
            $this->addFlash('danger', 'user.cannot_lock_yourself');

            return;
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
