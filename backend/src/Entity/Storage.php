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
use App\Repository\StorageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StorageRepository::class)]
#[ApiResource(
    operations: [
        new Get(),
        new Patch(securityPostDenormalize: 'object.getRoom()?.getPlace()?.getOwner() == user'),
        new Delete(),
        new Post(securityPostDenormalize: 'object.getRoom()?.getPlace()?.getOwner() == user'),
    ],
    normalizationContext: ['groups' => ['storage:read']],
    denormalizationContext: ['groups' => ['storage:write']],
)]
#[ApiResource(
    uriTemplate: '/rooms/{roomId}/storages',
    operations: [new GetCollection(paginationEnabled: false)],
    uriVariables: ['roomId' => new Link(toProperty: 'room', fromClass: Room::class)],
    normalizationContext: ['groups' => ['storage:read']],
)]
class Storage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['storage:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['storage:read', 'storage:write'])]
    private ?string $name = null;

    // Privé par défaut : c'est le propriétaire qui ouvre
    #[ORM\Column(length: 50, enumType: Visibility::class, options: ['default' => 'private'])]
    #[Groups(['storage:read', 'storage:write'])]
    private Visibility $visibility = Visibility::Private;

    // Perso : visible uniquement par le propriétaire, prioritaire sur la visibilité
    #[ORM\Column(options: ['default' => false])]
    #[Groups(['storage:read', 'storage:write'])]
    private bool $isPersonal = false;

    #[ORM\ManyToOne(inversedBy: 'storages')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['storage:read', 'storage:write'])]
    private ?Room $room = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(mappedBy: 'storage', targetEntity: Category::class, orphanRemoval: true)]
    private Collection $categories;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
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

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): static
    {
        $this->room = $room;

        return $this;
    }

    // Nombre d'objets rangés dans toutes les catégories du rangement
    #[Groups(['storage:read'])]
    public function getItemCount(): int
    {
        return array_sum($this->categories->map(fn (Category $category) => $category->getItemCount())->toArray());
    }

    #[Groups(['storage:read'])]
    public function getCategoryCount(): int
    {
        return $this->categories->count();
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setStorage($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            if ($category->getStorage() === $this) {
                $category->setStorage(null);
            }
        }

        return $this;
    }
}
