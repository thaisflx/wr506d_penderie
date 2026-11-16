<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Catégories (docs/decisions.md, « Back office ») : nom, type et nombre d'objets, sans le rangement ni la pièce où
 * elles se trouvent. Nom et type modifiables ; suppression seulement si la catégorie est vide (sinon ses objets
 * partiraient avec elle) ; pas de création, une catégorie appartient au rangement d'un utilisateur.
 *
 * @extends AbstractCrudController<Category>
 */
final class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('category.singular')
            ->setEntityLabelInPlural('category.plural')
            ->setSearchFields(['name'])
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $onlyIfEmpty = static fn (Action $action) => $action->displayIf(static fn (Category $category) => $category->getItems()->isEmpty());

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::BATCH_DELETE)
            ->update(Crud::PAGE_INDEX, Action::DELETE, $onlyIfEmpty)
            ->update(Crud::PAGE_DETAIL, Action::DELETE, $onlyIfEmpty);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'category.name');
        yield ChoiceField::new('type', 'category.type');
        yield IntegerField::new('items', 'category.item_count')->hideOnForm()
            ->formatValue(static fn (mixed $value, Category $category) => $category->getItems()->count());
    }

    // Le bouton est caché pour une catégorie pleine ; ceci refuse aussi une suppression envoyée sans passer par lui
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance->getItems()->isEmpty()) {
            $this->addFlash('danger', 'category.not_empty');

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
