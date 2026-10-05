<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $username = null;

    /**
     * @var Collection<int, Member>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Member::class, orphanRemoval: true)]
    private Collection $members;

    /**
     * @var Collection<int, ItemShare>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ItemShare::class, orphanRemoval: true)]
    private Collection $sharedItems;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(mappedBy: 'borrower', targetEntity: Reservation::class, orphanRemoval: true)]
    private Collection $receivedReservations;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->sharedItems = new ArrayCollection();
        $this->receivedReservations = new ArrayCollection();
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @return Collection<int, Member>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(Member $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setUser($this);
        }

        return $this;
    }

    public function removeMember(Member $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getUser() === $this) {
                $member->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ItemShare>
     */
    public function getSharedItems(): Collection
    {
        return $this->sharedItems;
    }

    public function addSharedItem(ItemShare $sharedItem): static
    {
        if (!$this->sharedItems->contains($sharedItem)) {
            $this->sharedItems->add($sharedItem);
            $sharedItem->setUser($this);
        }

        return $this;
    }

    public function removeSharedItem(ItemShare $sharedItem): static
    {
        if ($this->sharedItems->removeElement($sharedItem)) {
            if ($sharedItem->getUser() === $this) {
                $sharedItem->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReceivedReservations(): Collection
    {
        return $this->receivedReservations;
    }

    public function addReceivedReservation(Reservation $reservation): static
    {
        if (!$this->receivedReservations->contains($reservation)) {
            $this->receivedReservations->add($reservation);
            $reservation->setBorrower($this);
        }

        return $this;
    }

    public function removeReceivedReservation(Reservation $reservation): static
    {
        if ($this->receivedReservations->removeElement($reservation)) {
            if ($reservation->getBorrower() === $this) {
                $reservation->setBorrower(null);
            }
        }

        return $this;
    }
}
