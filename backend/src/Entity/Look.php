<?php

namespace App\Entity;

use App\Repository\LookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LookRepository::class)]
class Look
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // Un look n'est visible par les autres qu'une fois publié
    #[ORM\Column(options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\ManyToOne(inversedBy: 'looks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    /**
     * @var Collection<int, LookItem>
     */
    #[ORM\OneToMany(mappedBy: 'look', targetEntity: LookItem::class, cascade: ['REMOVE'], orphanRemoval: true)]
    private Collection $lookItems;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'look', targetEntity: Comment::class, orphanRemoval: true)]
    private Collection $comments;

    /**
     * @var Collection<int, ShareLink>
     */
    #[ORM\OneToMany(mappedBy: 'look', targetEntity: ShareLink::class)]
    private Collection $shareLinks;

    public function __construct()
    {
        $this->lookItems = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->shareLinks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    // Renseigne la date de publication à la première publication
    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
        if ($isPublished && null === $this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    /**
     * @return Collection<int, LookItem>
     */
    public function getLookItems(): Collection
    {
        return $this->lookItems;
    }

    public function addLookItem(LookItem $lookItem): static
    {
        if (!$this->lookItems->contains($lookItem)) {
            $this->lookItems->add($lookItem);
            $lookItem->setLook($this);
        }

        return $this;
    }

    public function removeLookItem(LookItem $lookItem): static
    {
        if ($this->lookItems->removeElement($lookItem)) {
            if ($lookItem->getLook() === $this) {
                $lookItem->setLook(null);
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
            $comment->setLook($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            if ($comment->getLook() === $this) {
                $comment->setLook(null);
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
}
