<?php

namespace App\Entity;

use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Repository\RelationshipRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande d'abonnement ou d'ami proche.
 * Le demandeur (requester) obtient des droits sur le contenu du destinataire (addressee)
 * uniquement quand le destinataire a accepté la demande.
 */
#[ORM\Entity(repositoryClass: RelationshipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_relationship_requester_addressee_type', fields: ['requester', 'addressee', 'type'])]
class Relationship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sentRelationships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $requester = null;

    #[ORM\ManyToOne(inversedBy: 'receivedRelationships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $addressee = null;

    #[ORM\Column(length: 50, enumType: RelationshipType::class)]
    private RelationshipType $type;

    #[ORM\Column(length: 50, enumType: RelationshipStatus::class)]
    private RelationshipStatus $status = RelationshipStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getAddressee(): ?User
    {
        return $this->addressee;
    }

    public function setAddressee(?User $addressee): static
    {
        $this->addressee = $addressee;

        return $this;
    }

    public function getType(): RelationshipType
    {
        return $this->type;
    }

    public function setType(RelationshipType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getStatus(): RelationshipStatus
    {
        return $this->status;
    }

    public function setStatus(RelationshipStatus $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isAccepted(): bool
    {
        return RelationshipStatus::Accepted === $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
