<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Nullable : un compte enfant créé et géré par son parent n'a pas d'email
    #[ORM\Column(length: 180, unique: true, nullable: true)]
    private ?string $email = null;

    // Nullable : un compte enfant sans email ne se connecte pas lui-même
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 50)]
    private ?string $username = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    // Sert à calculer si le compte est mineur (-18)
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $bio = null;

    // Modération par un admin
    #[ORM\Column(options: ['default' => false])]
    private bool $isSuspended = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // Compte parent : si le parent est supprimé, l'enfant devient autonome
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $parent = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    /**
     * @var Collection<int, Home>
     */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Home::class, orphanRemoval: true)]
    private Collection $homes;

    /**
     * @var Collection<int, Item>
     */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Item::class, orphanRemoval: true)]
    private Collection $items;

    /**
     * @var Collection<int, Look>
     */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Look::class, orphanRemoval: true)]
    private Collection $looks;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'author', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    /**
     * @var Collection<int, ShareLink>
     */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: ShareLink::class, orphanRemoval: true)]
    private Collection $shareLinks;

    /**
     * Demandes envoyées (abonnement ou ami proche)
     *
     * @var Collection<int, Relationship>
     */
    #[ORM\OneToMany(mappedBy: 'requester', targetEntity: Relationship::class, orphanRemoval: true)]
    private Collection $sentRelationships;

    /**
     * Demandes reçues (abonnés et amis proches de ce compte)
     *
     * @var Collection<int, Relationship>
     */
    #[ORM\OneToMany(mappedBy: 'addressee', targetEntity: Relationship::class, orphanRemoval: true)]
    private Collection $receivedRelationships;

    /**
     * @var Collection<int, ItemShare>
     */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ItemShare::class, orphanRemoval: true)]
    private Collection $sharedItems;

    /**
     * Prêts reçus (cet utilisateur est l'emprunteur)
     *
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(mappedBy: 'borrower', targetEntity: Reservation::class, orphanRemoval: true)]
    private Collection $receivedReservations;

    /**
     * @var Collection<int, Sale>
     */
    #[ORM\OneToMany(mappedBy: 'buyer', targetEntity: Sale::class)]
    private Collection $purchases;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->children = new ArrayCollection();
        $this->homes = new ArrayCollection();
        $this->items = new ArrayCollection();
        $this->looks = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->shareLinks = new ArrayCollection();
        $this->sentRelationships = new ArrayCollection();
        $this->receivedRelationships = new ArrayCollection();
        $this->sharedItems = new ArrayCollection();
        $this->receivedReservations = new ArrayCollection();
        $this->purchases = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
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
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    // Mineur = moins de 18 ans à la date donnée (aujourd'hui par défaut)
    public function isMinor(?\DateTimeImmutable $at = null): bool
    {
        if (null === $this->birthDate) {
            return false;
        }

        return $this->birthDate->diff($at ?? new \DateTimeImmutable())->y < 18;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): static
    {
        $this->isSuspended = $isSuspended;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getParent(): ?User
    {
        return $this->parent;
    }

    public function setParent(?User $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(User $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(User $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Home>
     */
    public function getHomes(): Collection
    {
        return $this->homes;
    }

    public function addHome(Home $home): static
    {
        if (!$this->homes->contains($home)) {
            $this->homes->add($home);
            $home->setOwner($this);
        }

        return $this;
    }

    public function removeHome(Home $home): static
    {
        if ($this->homes->removeElement($home)) {
            if ($home->getOwner() === $this) {
                $home->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Item>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(Item $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOwner($this);
        }

        return $this;
    }

    public function removeItem(Item $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOwner() === $this) {
                $item->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Look>
     */
    public function getLooks(): Collection
    {
        return $this->looks;
    }

    public function addLook(Look $look): static
    {
        if (!$this->looks->contains($look)) {
            $this->looks->add($look);
            $look->setOwner($this);
        }

        return $this;
    }

    public function removeLook(Look $look): static
    {
        if ($this->looks->removeElement($look)) {
            if ($look->getOwner() === $this) {
                $look->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setAuthor($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getAuthor() === $this) {
                $comment->setAuthor(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ShareLink>
     */
    public function getShareLinks(): Collection
    {
        return $this->shareLinks;
    }

    public function addShareLink(ShareLink $shareLink): static
    {
        if (!$this->shareLinks->contains($shareLink)) {
            $this->shareLinks->add($shareLink);
            $shareLink->setOwner($this);
        }

        return $this;
    }

    public function removeShareLink(ShareLink $shareLink): static
    {
        if ($this->shareLinks->removeElement($shareLink)) {
            if ($shareLink->getOwner() === $this) {
                $shareLink->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Relationship>
     */
    public function getSentRelationships(): Collection
    {
        return $this->sentRelationships;
    }

    public function addSentRelationship(Relationship $relationship): static
    {
        if (!$this->sentRelationships->contains($relationship)) {
            $this->sentRelationships->add($relationship);
            $relationship->setRequester($this);
        }

        return $this;
    }

    public function removeSentRelationship(Relationship $relationship): static
    {
        if ($this->sentRelationships->removeElement($relationship)) {
            if ($relationship->getRequester() === $this) {
                $relationship->setRequester(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Relationship>
     */
    public function getReceivedRelationships(): Collection
    {
        return $this->receivedRelationships;
    }

    public function addReceivedRelationship(Relationship $relationship): static
    {
        if (!$this->receivedRelationships->contains($relationship)) {
            $this->receivedRelationships->add($relationship);
            $relationship->setAddressee($this);
        }

        return $this;
    }

    public function removeReceivedRelationship(Relationship $relationship): static
    {
        if ($this->receivedRelationships->removeElement($relationship)) {
            if ($relationship->getAddressee() === $this) {
                $relationship->setAddressee(null);
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

    /**
     * @return Collection<int, Sale>
     */
    public function getPurchases(): Collection
    {
        return $this->purchases;
    }

    public function addPurchase(Sale $sale): static
    {
        if (!$this->purchases->contains($sale)) {
            $this->purchases->add($sale);
            $sale->setBuyer($this);
        }

        return $this;
    }

    public function removePurchase(Sale $sale): static
    {
        if ($this->purchases->removeElement($sale)) {
            if ($sale->getBuyer() === $this) {
                $sale->setBuyer(null);
            }
        }

        return $this;
    }
}
