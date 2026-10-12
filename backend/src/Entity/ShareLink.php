<?php

namespace App\Entity;

use App\Repository\ShareLinkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Lien de partage pour une personne sans compte.
 * Pointe vers un look OU une sélection d'objets, jamais vers une pièce.
 * N'expose qu'un aperçu : photo principale, nom, catégorie.
 */
#[ORM\Entity(repositoryClass: ShareLinkRepository::class)]
class ShareLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\ManyToOne(inversedBy: 'shareLinks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\ManyToOne(inversedBy: 'shareLinks')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Look $look = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\ManyToMany(targetEntity: Item::class)]
    private Collection $items;

    public function __construct()
    {
        // Jeton aléatoire non devinable
        $this->token = bin2hex(random_bytes(32));
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validateTarget(ExecutionContextInterface $context): void
    {
        if ((null === $this->look) === $this->items->isEmpty()) {
            $context->buildViolation('Un lien de partage doit pointer soit vers un look, soit vers une sélection d\'objets.')
                ->atPath('look')
                ->addViolation();
        }
    }

    // Un lien est actif s'il n'est ni révoqué ni expiré
    public function isActive(?\DateTimeImmutable $at = null): bool
    {
        $at ??= new \DateTimeImmutable();

        return null === $this->revokedAt && (null === $this->expiresAt || $this->expiresAt > $at);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeImmutable $revokedAt): static
    {
        $this->revokedAt = $revokedAt;

        return $this;
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

    public function getLook(): ?Look
    {
        return $this->look;
    }

    public function setLook(?Look $look): static
    {
        $this->look = $look;

        return $this;
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
