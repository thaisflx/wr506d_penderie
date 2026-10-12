<?php

namespace App\Entity;

use App\Enum\StatutReservation;
use App\Enum\TypeReservation;
use App\Repository\ReservationPretRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationPretRepository::class)]
class ReservationPret
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, enumType: TypeReservation::class)]
    private TypeReservation $type;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFinPrevue = null;

    #[ORM\Column(length: 50, enumType: StatutReservation::class)]
    private StatutReservation $statut;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    #[ORM\ManyToOne(inversedBy: 'reservationsRecues')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $emprunteurUser = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): TypeReservation
    {
        return $this->type;
    }

    public function setType(TypeReservation $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFinPrevue(): ?\DateTimeInterface
    {
        return $this->dateFinPrevue;
    }

    public function setDateFinPrevue(?\DateTimeInterface $dateFinPrevue): static
    {
        $this->dateFinPrevue = $dateFinPrevue;

        return $this;
    }

    public function getStatut(): StatutReservation
    {
        return $this->statut;
    }

    public function setStatut(StatutReservation $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

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

    public function getEmprunteurUser(): ?Utilisateur
    {
        return $this->emprunteurUser;
    }

    public function setEmprunteurUser(?Utilisateur $emprunteurUser): static
    {
        $this->emprunteurUser = $emprunteurUser;

        return $this;
    }
}
