<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Enum\Visibility;
use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new Patch(securityPostDenormalize: 'object.getPlace()?.getOwner() == user'),
        new Delete(),
        new Post(securityPostDenormalize: 'object.getPlace()?.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['room:read']],
    denormalizationContext: ['groups' => ['room:write']],
)]
#[ApiResource(
    uriTemplate: '/places/{placeId}/rooms',
    operations: [new GetCollection()],
    uriVariables: ['placeId' => new Link(toProperty: 'place', fromClass: Place::class)],
    normalizationContext: ['groups' => ['room:read']],
)]
class Room
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['room:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['room:read', 'room:write'])]
    private ?string $name = null;

    // Privé par défaut : c'est le propriétaire qui ouvre
    #[ORM\Column(length: 50, enumType: Visibility::class, options: ['default' => 'private'])]
    #[Groups(['room:read', 'room:write'])]
    private Visibility $visibility = Visibility::Private;

    // Perso : visible uniquement par le propriétaire, prioritaire sur la visibilité
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['room:read', 'room:write'])]
    private bool $isPersonal = false;

    #[ORM\ManyToOne(inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['room:read', 'room:write'])]
    private ?Place $place = null;

    /**
     * @var Collection<int, Storage>
     */
    #[ORM\OneToMany(mappedBy: 'room', targetEntity: Storage::class, orphanRemoval: true)]
    private Collection $storages;

    public function __construct()
    {
        $this->storages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVisibility(): Visibility
    {
        return $this->visibility;
    }

    public function setVisibility(Visibility $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    // Accesseur lu par le serializer pour exposer le champ "isPersonal"
    public function getIsPersonal(): bool
    {
        return $this->isPersonal;
    }

    public function isPersonal(): bool
    {
        return $this->isPersonal;
    }

    public function setIsPersonal(bool $isPersonal): static
    {
        $this->isPersonal = $isPersonal;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPlace(): ?Place
    {
        return $this->place;
    }

    public function setPlace(?Place $place): static
    {
        $this->place = $place;

        return $this;
    }

    #[Groups(['room:read'])]
    public function getStorageCount(): int
    {
        return $this->storages->count();
    }

    /**
     * @return Collection<int, Storage>
     */
    public function getStorages(): Collection
    {
        return $this->storages;
    }

    public function addStorage(Storage $storage): static
    {
        if (!$this->storages->contains($storage)) {
            $this->storages->add($storage);
            $storage->setRoom($this);
        }

        return $this;
    }

    public function removeStorage(Storage $storage): static
    {
        if ($this->storages->removeElement($storage)) {
            if ($storage->getRoom() === $this) {
                $storage->setRoom(null);
            }
        }

        return $this;
    }
}
