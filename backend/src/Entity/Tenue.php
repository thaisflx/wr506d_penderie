<?php

namespace App\Entity;

use App\Repository\TenueRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TenueRepository::class)]
class Tenue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'tenues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Membre $membre = null;

    /**
     * @var Collection<int, TenueObjet>
     */
    #[ORM\OneToMany(mappedBy: 'tenue', targetEntity: TenueObjet::class, cascade: ['REMOVE'], orphanRemoval: true)]
    private Collection $tenueObjets;

    public function __construct()
    {
        $this->tenueObjets = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getMembre(): ?Membre
    {
        return $this->membre;
    }

    public function setMembre(?Membre $membre): static
    {
        $this->membre = $membre;

        return $this;
    }

    /**
     * @return Collection<int, TenueObjet>
     */
    public function getTenueObjets(): Collection
    {
        return $this->tenueObjets;
    }

    public function addTenueObjet(TenueObjet $tenueObjet): static
    {
        if (!$this->tenueObjets->contains($tenueObjet)) {
            $this->tenueObjets->add($tenueObjet);
            $tenueObjet->setTenue($this);
        }

        return $this;
    }

    public function removeTenueObjet(TenueObjet $tenueObjet): static
    {
        if ($this->tenueObjets->removeElement($tenueObjet)) {
            if ($tenueObjet->getTenue() === $this) {
                $tenueObjet->setTenue(null);
            }
        }

        return $this;
    }
}
