<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $firstName = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'ownerMember', targetEntity: Item::class, orphanRemoval: true)]
    private Collection $items;

    /**
     * @var Collection<int, Look>
     */
    #[ORM\OneToMany(mappedBy: 'member', targetEntity: Look::class, orphanRemoval: true)]
    private Collection $looks;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->looks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addObject(Item $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOwnerMember($this);
        }

        return $this;
    }

    public function removeObject(Item $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOwnerMember() === $this) {
                $item->setOwnerMember(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Look>
     */
    public function getLooks(): Collection
    {
        return $this->looks;
    }

    public function addLook(Look $look): static
    {
        if (!$this->looks->contains($look)) {
            $this->looks->add($look);
            $look->setMember($this);
        }

        return $this;
    }

    public function removeLook(Look $look): static
    {
        if ($this->looks->removeElement($look)) {
            if ($look->getMember() === $this) {
                $look->setMember(null);
            }
        }

        return $this;
    }
}
