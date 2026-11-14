<?php

namespace App\Entity;

use App\Repository\OotdRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
class Ootd
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    // URL publique de la photo (uploads/ootds) ; un brouillon peut ne pas en avoir
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'La légende ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $caption = null;

    // Jour où la tenue a été portée : aujourd'hui par défaut, jamais dans le futur
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\LessThanOrEqual('today', message: 'La date du look ne peut pas être dans le futur.')]
    private \DateTimeImmutable $wornOn;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
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
    private Collection $items;

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
            $context->buildViolation('Ajoute une photo de ta tenue avant de publier.')
                ->atPath('photo')
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

    public function isPublished(): bool
    {
        return null !== $this->publishedAt;
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
