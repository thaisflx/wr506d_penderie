<?php

namespace App\Entity;

use App\Repository\LookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LookRepository::class)]
class Look
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(inversedBy: 'looks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Member $member = null;

    /**
     * @var Collection<int, LookItem>
     */
    #[ORM\OneToMany(mappedBy: 'look', targetEntity: LookItem::class, cascade: ['REMOVE'], orphanRemoval: true)]
    private Collection $lookItems;

    public function __construct()
    {
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

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

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
            $lookItem->setLook($this);
        }

        return $this;
    }

    public function removeLookItem(LookItem $lookItem): static
    {
        if ($this->lookItems->removeElement($lookItem)) {
            if ($lookItem->getLook() === $this) {
                $lookItem->setLook(null);
            }
        }

        return $this;
    }
}
