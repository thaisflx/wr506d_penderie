<?php

namespace App\Entity;

use App\Repository\PieceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PieceRepository::class)]
class Piece
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\ManyToOne(inversedBy: 'pieces')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Lieu $lieu = null;

    /**
     * @var Collection<int, Rangement>
     */
    #[ORM\OneToMany(mappedBy: 'piece', targetEntity: Rangement::class, orphanRemoval: true)]
    private Collection $rangements;

    public function __construct()
    {
        $this->rangements = new ArrayCollection();
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

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    /**
     * @return Collection<int, Rangement>
     */
    public function getRangements(): Collection
    {
        return $this->rangements;
    }

    public function addRangement(Rangement $rangement): static
    {
        if (!$this->rangements->contains($rangement)) {
            $this->rangements->add($rangement);
            $rangement->setPiece($this);
        }

        return $this;
    }

    public function removeRangement(Rangement $rangement): static
    {
        if ($this->rangements->removeElement($rangement)) {
            if ($rangement->getPiece() === $this) {
                $rangement->setPiece(null);
            }
        }

        return $this;
    }
}
