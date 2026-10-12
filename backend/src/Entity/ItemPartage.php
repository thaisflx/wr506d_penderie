<?php

namespace App\Entity;

use App\Repository\ItemPartageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemPartageRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_item_partage_item_user', fields: ['item', 'user'])]
class ItemPartage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'partages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    #[ORM\ManyToOne(inversedBy: 'objetsPartages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?Utilisateur
    {
        return $this->user;
    }

    public function setUser(?Utilisateur $user): static
    {
        $this->user = $user;

        return $this;
    }
}
