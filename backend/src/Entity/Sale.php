<?php

namespace App\Entity;

use App\Repository\SaleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Mise en vente d'un objet (visible par les abonnés, contrairement aux prêts)
#[ORM\Entity(repositoryClass: SaleRepository::class)]
class Sale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private ?string $price = null;

    #[ORM\Column]
    private \DateTimeImmutable $listedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $soldAt = null;

    #[ORM\ManyToOne(inversedBy: 'sales')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Item $item = null;

    // Acheteur, s'il a un compte
    #[ORM\ManyToOne(inversedBy: 'purchases')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $buyer = null;

    public function __construct()
    {
        $this->listedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getListedAt(): \DateTimeImmutable
    {
        return $this->listedAt;
    }

    public function getSoldAt(): ?\DateTimeImmutable
    {
        return $this->soldAt;
    }

    public function setSoldAt(?\DateTimeImmutable $soldAt): static
    {
        $this->soldAt = $soldAt;

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

    public function getBuyer(): ?User
    {
        return $this->buyer;
    }

    public function setBuyer(?User $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }
}
