<?php

namespace App\Controller\Admin;

use App\Entity\Ootd;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modération des looks (docs/decisions.md, « Back office ») : seulement les looks publiés, déjà montrés à d'autres ;
 * l'admin les regarde et les supprime. Jamais les brouillons, même par leur adresse ; pas de création ni de
 * modification ; pas de notification à l'auteur.
 *
 * @extends AbstractCrudController<Ootd>
 */
final class OotdCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Ootd::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('look.singular')
            ->setEntityLabelInPlural('look.plural')
            ->setSearchFields(['caption', 'owner.username'])
            ->setDefaultSort(['publishedAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->disable(Action::NEW, Action::EDIT, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('photo', 'look.photo')->setTemplatePath('admin/field/photo.html.twig');
        yield TextField::new('owner.username', 'look.owner');
        yield TextareaField::new('caption', 'look.caption');
        // Objets portés, comme les voient les autres personnes : ceux devenus privés ou perso sont masqués
        yield Field::new('items', 'look.items')->setTemplatePath('admin/field/worn_items.html.twig');
        yield DateField::new('wornOn', 'look.worn_on');
        yield DateTimeField::new('publishedAt', 'look.published_at');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.publishedAt IS NOT NULL');
    }

    public function detail(AdminContext $context): KeyValueStore|Response
    {
        $this->denyDrafts($context);

        return parent::detail($context);
    }

    public function delete(AdminContext $context): KeyValueStore|Response
    {
        $this->denyDrafts($context);

        return parent::delete($context);
    }

    /**
     * Un brouillon n'est visible que par son auteur, y compris pour l'admin : 404, comme dans l'API.
     *
     * @param AdminContext<Ootd> $context
     */
    private function denyDrafts(AdminContext $context): void
    {
        $ootd = $context->getEntity()->getInstance();
        if (!$ootd instanceof Ootd || !$ootd->isPublished()) {
            throw $this->createNotFoundException();
        }
    }
}
