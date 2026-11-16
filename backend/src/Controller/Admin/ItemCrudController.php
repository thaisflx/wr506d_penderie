<?php

namespace App\Controller\Admin;

use App\Entity\Item;
use App\Enum\CategoryType;
use App\Repository\ItemRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Objets dans le back office (docs/decisions.md, « Back office ») : « le dressing doit être privé » (brief), donc
 * seulement les objets déjà montrés à d'autres, dans un look publié, et jamais leur emplacement. Lecture seule.
 *
 * @extends AbstractCrudController<Item>
 */
final class ItemCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ItemRepository $items,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Item::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('item.singular')
            ->setEntityLabelInPlural('item.plural')
            ->setSearchFields(['name'])
            ->setDefaultSort(['name' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('photo', 'item.photo')->setTemplatePath('admin/field/photo.html.twig');
        yield TextField::new('name', 'item.name');
        yield TextField::new('categoryType', 'item.type')
            ->formatValue(fn (?string $value) => null === $value ? null : CategoryType::from($value)->trans($this->translator));
        yield TextField::new('brand', 'item.brand');
        yield TextField::new('owner.username', 'item.owner');
        yield IntegerField::new('id', 'item.published_looks')
            ->formatValue(fn (mixed $value, Item $item) => $this->items->countPublishedLooks($item));
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return $this->items->restrictToShownInPublishedLooks(parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters), 'entity');
    }

    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $item = $context->getEntity()->getInstance();
        if (!$item instanceof Item || !$this->items->isShownInPublishedLooks($item)) {
            throw $this->createNotFoundException();
        }

        return parent::detail($context);
    }
}
