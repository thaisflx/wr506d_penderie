<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\RelationshipStatus;
use App\Enum\RelationshipType;
use App\Repository\RelationshipRepository;
use App\State\RelationshipProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Demande d'abonnement ou d'ami proche.
 * Le demandeur (requester) obtient des droits sur le contenu du destinataire (addressee)
 * uniquement quand le destinataire a accepté la demande.
 */
#[ORM\Entity(repositoryClass: RelationshipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_relationship_requester_addressee_type', fields: ['requester', 'addressee', 'type'])]
#[UniqueEntity(fields: ['requester', 'addressee', 'type'], message: 'Tu as déjà envoyé cette demande.', errorPath: 'addressee')]
// Seules les relations où je suis demandeur ou destinataire existent pour moi : App\Doctrine\RelationshipExtension
#[ApiResource(
    operations: [
        // Filtres : ?direction=received|sent, ?status=pending|accepted, ?type=follower|close_friend
        new GetCollection(order: ['createdAt' => 'DESC'], paginationEnabled: false),
        new Get(),
        // Envoyer une demande : le demandeur est toujours l'utilisateur connecté, la demande toujours en attente
        new Post(
            denormalizationContext: ['groups' => ['relationship:create'], 'denormalize_throw_on_relation_not_found' => false],
            processor: RelationshipProcessor::class,
        ),
        // Accepter une demande reçue (pending -> accepted)
        new Patch(
            denormalizationContext: ['groups' => ['relationship:accept']],
            security: 'object.getAddressee() == user',
            processor: RelationshipProcessor::class,
        ),
        // Annuler, se désabonner (demandeur) ; refuser, retirer un abonné (destinataire)
        new Delete(security: 'object.getRequester() == user or object.getAddressee() == user'),
    ],
    normalizationContext: ['groups' => ['relationship:read', 'user:public']],
    security: "is_granted('ROLE_USER')",
)]
class Relationship
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['relationship:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sentRelationships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['relationship:read'])]
    private ?User $requester = null;

    #[ORM\ManyToOne(inversedBy: 'receivedRelationships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['relationship:read', 'relationship:create'])]
    private ?User $addressee = null;

    #[ORM\Column(length: 50, enumType: RelationshipType::class)]
    #[Assert\NotNull(message: 'Indique le type de demande : follower ou close_friend.')]
    #[Groups(['relationship:read', 'relationship:create'])]
    private ?RelationshipType $type = null;

    #[ORM\Column(length: 50, enumType: RelationshipStatus::class)]
    #[Groups(['relationship:read', 'relationship:accept'])]
    private RelationshipStatus $status = RelationshipStatus::Pending;

    #[ORM\Column]
    #[Groups(['relationship:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validateNotSelf(ExecutionContextInterface $context): void
    {
        if (null !== $this->requester && $this->requester === $this->addressee) {
            $context->buildViolation('Tu ne peux pas t\'envoyer une demande à toi-même.')
                ->atPath('addressee')
                ->addViolation();
        }
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

    public function getType(): ?RelationshipType
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
