<?php

namespace App\Entity;

use App\Enum\StatutObjet;
use App\Enum\VisibiliteObjet;
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
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeBarres = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attributs = null;

    #[ORM\Column(length: 50, enumType: StatutObjet::class)]
    private StatutObjet $statut;

    #[ORM\Column(length: 50, enumType: VisibiliteObjet::class)]
    private VisibiliteObjet $visibilite;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Categorie $categorie = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rangement $rangement = null;

    #[ORM\ManyToOne(inversedBy: 'objets')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Membre $proprietaireMembre = null;

    /**
     * @var Collection<int, ReservationPret>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: ReservationPret::class, orphanRemoval: true)]
    private Collection $reservations;

    /**
     * @var Collection<int, ItemPartage>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: ItemPartage::class, orphanRemoval: true)]
    private Collection $partages;

    /**
     * @var Collection<int, TenueObjet>
     */
    #[ORM\OneToMany(mappedBy: 'item', targetEntity: TenueObjet::class, orphanRemoval: true)]
    private Collection $tenueObjets;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
        $this->partages = new ArrayCollection();
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

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getCodeBarres(): ?string
    {
        return $this->codeBarres;
    }

    public function setCodeBarres(?string $codeBarres): static
    {
        $this->codeBarres = $codeBarres;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAttributs(): ?array
    {
        return $this->attributs;
    }

    /**
     * @param array<string, mixed>|null $attributs
     */
    public function setAttributs(?array $attributs): static
    {
        $this->attributs = $attributs;

        return $this;
    }

    public function getStatut(): StatutObjet
    {
        return $this->statut;
    }

    public function setStatut(StatutObjet $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getVisibilite(): VisibiliteObjet
    {
        return $this->visibilite;
    }

    public function setVisibilite(VisibiliteObjet $visibilite): static
    {
        $this->visibilite = $visibilite;

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getRangement(): ?Rangement
    {
        return $this->rangement;
    }

    public function setRangement(?Rangement $rangement): static
    {
        $this->rangement = $rangement;

        return $this;
    }

    public function getProprietaireMembre(): ?Membre
    {
        return $this->proprietaireMembre;
    }

    public function setProprietaireMembre(?Membre $proprietaireMembre): static
    {
        $this->proprietaireMembre = $proprietaireMembre;

        return $this;
    }

    /**
     * @return Collection<int, ReservationPret>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(ReservationPret $reservationPret): static
    {
        if (!$this->reservations->contains($reservationPret)) {
            $this->reservations->add($reservationPret);
            $reservationPret->setItem($this);
        }

        return $this;
    }

    public function removeReservation(ReservationPret $reservationPret): static
    {
        if ($this->reservations->removeElement($reservationPret)) {
            if ($reservationPret->getItem() === $this) {
                $reservationPret->setItem(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ItemPartage>
     */
    public function getPartages(): Collection
    {
        return $this->partages;
    }

    public function addPartage(ItemPartage $itemPartage): static
    {
        if (!$this->partages->contains($itemPartage)) {
            $this->partages->add($itemPartage);
            $itemPartage->setItem($this);
        }

        return $this;
    }

    public function removePartage(ItemPartage $itemPartage): static
    {
        if ($this->partages->removeElement($itemPartage)) {
            if ($itemPartage->getItem() === $this) {
                $itemPartage->setItem(null);
            }
        }

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
            $tenueObjet->setItem($this);
        }

        return $this;
    }

    public function removeTenueObjet(TenueObjet $tenueObjet): static
    {
        if ($this->tenueObjets->removeElement($tenueObjet)) {
            if ($tenueObjet->getItem() === $this) {
                $tenueObjet->setItem(null);
            }
        }

        return $this;
    }
}
