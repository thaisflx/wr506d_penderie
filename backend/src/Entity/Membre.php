<?php

namespace App\Entity;

use App\Repository\MembreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MembreRepository::class)]
class Membre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $user = null;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'proprietaireMembre', targetEntity: Item::class, orphanRemoval: true)]
    private Collection $objets;

    /**
     * @var Collection<int, Tenue>
     */
    #[ORM\OneToMany(mappedBy: 'membre', targetEntity: Tenue::class, orphanRemoval: true)]
    private Collection $tenues;

    public function __construct()
    {
        $this->objets = new ArrayCollection();
        $this->tenues = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

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

    /**
     * @return Collection<int, Item>
     */
    public function getObjets(): Collection
    {
        return $this->objets;
    }

    public function addObject(Item $item): static
    {
        if (!$this->objets->contains($item)) {
            $this->objets->add($item);
            $item->setProprietaireMembre($this);
        }

        return $this;
    }

    public function removeObject(Item $item): static
    {
        if ($this->objets->removeElement($item)) {
            if ($item->getProprietaireMembre() === $this) {
                $item->setProprietaireMembre(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tenue>
     */
    public function getTenues(): Collection
    {
        return $this->tenues;
    }

    public function addTenue(Tenue $tenue): static
    {
        if (!$this->tenues->contains($tenue)) {
            $this->tenues->add($tenue);
            $tenue->setMembre($this);
        }

        return $this;
    }

    public function removeTenue(Tenue $tenue): static
    {
        if ($this->tenues->removeElement($tenue)) {
            if ($tenue->getMembre() === $this) {
                $tenue->setMembre(null);
            }
        }

        return $this;
    }
}
