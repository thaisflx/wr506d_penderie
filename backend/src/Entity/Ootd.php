<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Doctrine\OotdVisibilityExtension;
use App\Repository\OotdRepository;
use App\State\OotdProcessor;
use App\Validator\ShareableItems;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Look du jour (OOTD) : une photo de ma tenue, une légende et les objets portés (voir docs/decisions.md).
 * Séparé de Look : un OOTD est une publication datée, un look une composition réutilisable.
 * Brouillon tant que publishedAt est vide : visible par le propriétaire seul.
 */
#[ORM\Entity(repositoryClass: OotdRepository::class)]
#[ORM\Index(name: 'idx_ootd_published_at', fields: ['publishedAt'])]
#[ORM\Index(name: 'idx_ootd_owner_published_at', fields: ['owner', 'publishedAt'])]
// Visibilité : App\Doctrine\OotdVisibilityExtension (404 si invisible) et App\Security\Voter\OotdVoter
#[ApiResource(
    operations: [
        // Fil : looks publiés que je peux voir, et les miens, du plus récent au plus ancien
        new GetCollection(
            name: OotdVisibilityExtension::FEED,
            order: ['publishedAt' => 'DESC', 'id' => 'DESC'],
            paginationItemsPerPage: 10,
        ),
        new Get(security: "is_granted('OOTD_VIEW', object)"),
        // Créé en brouillon, sauf avec "published": true (photo obligatoire)
        new Post(processor: OotdProcessor::class),
        new Patch(security: "is_granted('OOTD_EDIT', object)"),
        new Delete(security: "is_granted('OOTD_EDIT', object)"),
    ],
    normalizationContext: ['groups' => ['ootd:read', 'ootd:item', 'user:public']],
    denormalizationContext: ['groups' => ['ootd:write']],
    security: "is_granted('ROLE_USER')",
)]
// Mes looks (profil) : brouillons et publiés, ?published=true|false
#[ApiResource(
    uriTemplate: '/me/ootds',
    operations: [new GetCollection(name: OotdVisibilityExtension::MINE, order: ['createdAt' => 'DESC', 'id' => 'DESC'], paginationEnabled: false)],
    normalizationContext: ['groups' => ['ootd:read', 'ootd:item', 'user:public']],
    security: "is_granted('ROLE_USER')",
)]
// Looks publiés d'un profil, avec la même règle que le fil
#[ApiResource(
    uriTemplate: '/users/{userId}/ootds',
    operations: [new GetCollection(
        name: OotdVisibilityExtension::USER,
        order: ['publishedAt' => 'DESC', 'id' => 'DESC'],
        paginationItemsPerPage: 10,
    )],
    uriVariables: ['userId' => new Link(fromClass: User::class, toProperty: 'owner')],
    normalizationContext: ['groups' => ['ootd:read', 'ootd:item', 'user:public']],
    security: "is_granted('ROLE_USER')",
)]
class Ootd
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ootd:read'])]
    private ?int $id = null;

    // Renseigné par OotdProcessor avec l'utilisateur connecté
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['ootd:read'])]
    private ?User $owner = null;

    // URL publique de la photo (uploads/ootds) ; un brouillon peut ne pas en avoir
    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['ootd:read'])]
    private ?string $photo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['ootd:read', 'ootd:write'])]
    #[Assert\Length(max: 500, maxMessage: 'ootd.caption_too_long')]
    private ?string $caption = null;

    // Jour où la tenue a été portée : aujourd'hui par défaut, jamais dans le futur
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['ootd:read', 'ootd:write'])]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    #[Assert\LessThanOrEqual('today', message: 'ootd.future_date')]
    private \DateTimeImmutable $wornOn;

    #[ORM\Column(nullable: true)]
    #[Groups(['ootd:read'])]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    #[Groups(['ootd:read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Objets portés : un objet supprimé disparaît du look (ON DELETE CASCADE sur la table de jointure).
     *
     * @var Collection<int, Item>
     */
    #[ORM\ManyToMany(targetEntity: Item::class)]
    #[ORM\JoinTable(name: 'ootd_item')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(onDelete: 'CASCADE')]
    // Ordre stable à l'affichage (sinon la base renvoie les objets dans n'importe quel ordre)
    #[ORM\OrderBy(['id' => 'ASC'])]
    #[Groups(['ootd:write'])]
    #[ShareableItems]
    private Collection $items;

    // Demande de repasser un look publié en brouillon : refusée à la validation (on supprime le look)
    private bool $unpublishRequested = false;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->wornOn = new \DateTimeImmutable('today');
    }

    // Un look publié a forcément une photo (un brouillon peut s'en passer)
    #[Assert\Callback]
    public function validatePhotoBeforePublishing(ExecutionContextInterface $context): void
    {
        if (null !== $this->publishedAt && null === $this->photo) {
            $context->buildViolation('ootd.photo_required')
                ->atPath('photo')
                ->addViolation();
        }
        if ($this->unpublishRequested) {
            $context->buildViolation('ootd.no_back_to_draft')
                ->atPath('published')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): static
    {
        $this->caption = $caption;

        return $this;
    }

    public function getWornOn(): \DateTimeImmutable
    {
        return $this->wornOn;
    }

    public function setWornOn(\DateTimeImmutable $wornOn): static
    {
        $this->wornOn = $wornOn;

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;

        return $this;
    }

    #[Groups(['ootd:read'])]
    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
    }

    // "published": true publie le look (date de publication fixée une seule fois)
    #[Groups(['ootd:write'])]
    public function setPublished(bool $published): static
    {
        if ($published && null === $this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        }
        $this->unpublishRequested = !$published && null !== $this->publishedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Objets portés, tels qu'affichés (ceux devenus privés sont retirés par App\Serializer\OotdNormalizer).
     *
     * @return list<Item>
     */
    #[Groups(['ootd:read'])]
    public function getWornItems(): array
    {
        return $this->items->getValues();
    }

    public function addItem(Item $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
        }

        return $this;
    }

    public function removeItem(Item $item): static
    {
        $this->items->removeElement($item);

        return $this;
    }
}
