<?php

namespace App\Entity;

use App\Repository\LookItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LookItemRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_look_item_look_item', fields: ['look', 'item'])]
class LookItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lookItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Look $look = null;

    #[ORM\ManyToOne(inversedBy: 'lookItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLook(): ?Look
    {
        return $this->look;
    }

    public function setLook(?Look $look): static
    {
        $this->look = $look;

        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;

        return $this;
    }
}
