<?php

namespace App\Entity;

use App\Enum\Visibility;
use App\Repository\RoomRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
class Room
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

    #[ORM\ManyToOne(inversedBy: 'rooms')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Home $home = null;

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

    public function getHome(): ?Home
    {
        return $this->home;
    }

    public function setHome(?Home $home): static
    {
        $this->home = $home;

        return $this;
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
