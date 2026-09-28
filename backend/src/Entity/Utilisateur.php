<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(length: 50)]
    private ?string $pseudo = null;

    /**
     * @var Collection<int, Membre>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Membre::class, orphanRemoval: true)]
    private Collection $membres;

    /**
     * @var Collection<int, ItemPartage>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ItemPartage::class, orphanRemoval: true)]
    private Collection $objetsPartages;

    /**
     * @var Collection<int, ReservationPret>
     */
    #[ORM\OneToMany(mappedBy: 'emprunteurUser', targetEntity: ReservationPret::class, orphanRemoval: true)]
    private Collection $reservationsRecues;

    public function __construct()
    {
        $this->membres = new ArrayCollection();
        $this->objetsPartages = new ArrayCollection();
        $this->reservationsRecues = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(string $pseudo): static
    {
        $this->pseudo = $pseudo;

        return $this;
    }

    /**
     * @return Collection<int, Membre>
     */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function addMembre(Membre $membre): static
    {
        if (!$this->membres->contains($membre)) {
            $this->membres->add($membre);
            $membre->setUser($this);
        }

        return $this;
    }

    public function removeMembre(Membre $membre): static
    {
        if ($this->membres->removeElement($membre)) {
            if ($membre->getUser() === $this) {
                $membre->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ItemPartage>
     */
    public function getObjetsPartages(): Collection
    {
        return $this->objetsPartages;
    }

    public function addObjetPartage(ItemPartage $objetPartage): static
    {
        if (!$this->objetsPartages->contains($objetPartage)) {
            $this->objetsPartages->add($objetPartage);
            $objetPartage->setUser($this);
        }

        return $this;
    }

    public function removeObjetPartage(ItemPartage $objetPartage): static
    {
        if ($this->objetsPartages->removeElement($objetPartage)) {
            if ($objetPartage->getUser() === $this) {
                $objetPartage->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ReservationPret>
     */
    public function getReservationsRecues(): Collection
    {
        return $this->reservationsRecues;
    }

    public function addReservationRecue(ReservationPret $reservationPret): static
    {
        if (!$this->reservationsRecues->contains($reservationPret)) {
            $this->reservationsRecues->add($reservationPret);
            $reservationPret->setEmprunteurUser($this);
        }

        return $this;
    }

    public function removeReservationRecue(ReservationPret $reservationPret): static
    {
        if ($this->reservationsRecues->removeElement($reservationPret)) {
            if ($reservationPret->getEmprunteurUser() === $this) {
                $reservationPret->setEmprunteurUser(null);
            }
        }

        return $this;
    }
}
