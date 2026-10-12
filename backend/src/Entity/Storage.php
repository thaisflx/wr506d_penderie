<?php

namespace App\Entity;

use App\Enum\Visibility;
use App\Repository\StorageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StorageRepository::class)]
class Storage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // Privé par défaut : c'est le propriétaire qui ouvre
    #[ORM\Column(length: 50, enumType: Visibility::class, options: ['default' => 'private'])]
    private Visibility $visibility = Visibility::Private;

    // Perso : visible uniquement par le propriétaire, prioritaire sur la visibilité
    #[ORM\Column(options: ['default' => false])]
    private bool $isPersonal = false;

    #[ORM\ManyToOne(inversedBy: 'storages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Room $room = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'storage', targetEntity: Item::class, orphanRemoval: true)]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
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
            $item->setStorage($this);
        }

        return $this;
    }

    public function removeItem(Item $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getStorage() === $this) {
                $item->setStorage(null);
            }
        }

        return $this;
    }
}
