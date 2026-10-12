<?php

namespace App\Entity;

use App\Repository\TenueObjetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenueObjetRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tenue_objet_tenue_item', fields: ['tenue', 'item'])]
class TenueObjet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tenueObjets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Tenue $tenue = null;

    #[ORM\ManyToOne(inversedBy: 'tenueObjets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTenue(): ?Tenue
    {
        return $this->tenue;
    }

    public function setTenue(?Tenue $tenue): static
    {
        $this->tenue = $tenue;

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
