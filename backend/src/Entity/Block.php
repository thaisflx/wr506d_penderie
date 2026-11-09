<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\BlockRepository;
use App\State\BlockProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Blocage d'une personne par une autre (voir docs/decisions.md).
 * Il agit dans les deux sens : ni le bloqueur ni la personne bloquée ne se voient plus.
 */
#[ORM\Entity(repositoryClass: BlockRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_block_blocker_blocked', fields: ['blocker', 'blocked'])]
#[UniqueEntity(fields: ['blocker', 'blocked'], message: 'Cette personne est déjà bloquée.', errorPath: 'blocked')]
// Je ne vois que mes propres blocages : la personne bloquée ne sait pas qu'elle l'est (App\Doctrine\BlockExtension)
#[ApiResource(
    operations: [
        new GetCollection(order: ['createdAt' => 'DESC'], paginationEnabled: false),
        new Get(),
        // Bloquer : supprime aussi toutes les relations entre les deux personnes
        new Post(
            denormalizationContext: ['groups' => ['block:create'], 'denormalize_throw_on_relation_not_found' => false],
            processor: BlockProcessor::class,
        ),
        // Débloquer : les relations supprimées ne reviennent pas
        new Delete(security: 'object.getBlocker() == user'),
    ],
    normalizationContext: ['groups' => ['block:read', 'user:public']],
    security: "is_granted('ROLE_USER')",
)]
class Block
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['block:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $blocker = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['block:read', 'block:create'])]
    private ?User $blocked = null;

    #[ORM\Column]
    #[Groups(['block:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateNotSelf(ExecutionContextInterface $context): void
    {
        if (null !== $this->blocker && $this->blocker === $this->blocked) {
            $context->buildViolation('Tu ne peux pas te bloquer toi-même.')
                ->atPath('blocked')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlocker(): ?User
    {
        return $this->blocker;
    }

    public function setBlocker(?User $blocker): static
    {
        $this->blocker = $blocker;

        return $this;
    }

    public function getBlocked(): ?User
    {
        return $this->blocked;
    }

    public function setBlocked(?User $blocked): static
    {
        $this->blocked = $blocked;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
