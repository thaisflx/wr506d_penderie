<?php

namespace App\Entity;

use App\Enum\ItemStatus;
use App\Enum\Visibility;
use App\Repository\ItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $barcode = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attributes = null;

    #[ORM\Column(length: 50, enumType: ItemStatus::class)]
    private ItemStatus $status;

    #[ORM\Column(length: 50, enumType: Visibility::class)]
    private Visibility $visibility;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Storage $storage = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Member $ownerMember = null;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: Reservation::class, orphanRemoval: true)]
    private Collection $reservations;

    /**
     * @var Collection<int, ItemShare>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: ItemShare::class, orphanRemoval: true)]
    private Collection $shares;

    /**
     * @var Collection<int, LookItem>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: LookItem::class, orphanRemoval: true)]
    private Collection $lookItems;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->shares = new ArrayCollection();
        $this->lookItems = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getBarcode(): ?string
    {
        return $this->barcode;
    }

    public function setBarcode(?string $barcode): static
    {
        $this->barcode = $barcode;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, mixed>|null $attributes
     */
    public function setAttributes(?array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getStatus(): ItemStatus
    {
        return $this->status;
    }

    public function setStatus(ItemStatus $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getStorage(): ?Storage
    {
        return $this->storage;
    }

    public function setStorage(?Storage $storage): static
    {
        $this->storage = $storage;

        return $this;
    }

    public function getOwnerMember(): ?Member
    {
        return $this->ownerMember;
    }

    public function setOwnerMember(?Member $ownerMember): static
    {
        $this->ownerMember = $ownerMember;

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setItem($this);
        }

        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getItem() === $this) {
                $reservation->setItem(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ItemShare>
     */
    public function getShares(): Collection
    {
        return $this->shares;
    }

    public function addShare(ItemShare $itemShare): static
    {
        if (!$this->shares->contains($itemShare)) {
            $this->shares->add($itemShare);
            $itemShare->setItem($this);
        }

        return $this;
    }

    public function removeShare(ItemShare $itemShare): static
    {
        if ($this->shares->removeElement($itemShare)) {
            if ($itemShare->getItem() === $this) {
                $itemShare->setItem(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LookItem>
     */
    public function getLookItems(): Collection
    {
        return $this->lookItems;
    }

    public function addLookItem(LookItem $lookItem): static
    {
        if (!$this->lookItems->contains($lookItem)) {
            $this->lookItems->add($lookItem);
            $lookItem->setItem($this);
        }

        return $this;
    }

    public function removeLookItem(LookItem $lookItem): static
    {
        if ($this->lookItems->removeElement($lookItem)) {
            if ($lookItem->getItem() === $this) {
                $lookItem->setItem(null);
            }
        }

        return $this;
    }
}
