<?php

namespace App\EventListener;

use App\Entity\Ootd;
use App\Service\OotdPhotoStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

// Supprime le fichier de la photo quand le look du jour est supprimé
#[AsEntityListener(event: Events::postRemove, entity: Ootd::class)]
final class OotdPhotoCleaner
{
    public function __construct(private readonly OotdPhotoStorage $storage)
    {
    }

    public function postRemove(Ootd $ootd): void
    {
        $this->storage->remove($ootd);
    }
}
