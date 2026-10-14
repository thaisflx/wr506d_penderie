<?php

namespace App\EventListener;

use App\Entity\Item;
use App\Service\ItemPhotoStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

// Supprime le fichier de la photo quand l'objet est supprimé
#[AsEntityListener(event: Events::postRemove, entity: Item::class)]
final class ItemPhotoCleaner
{
    public function __construct(private readonly ItemPhotoStorage $storage)
    {
    }

    public function postRemove(Item $item): void
    {
        $this->storage->remove($item);
    }
}
