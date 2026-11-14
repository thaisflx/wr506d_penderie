<?php

namespace App\Service;

use App\Entity\Item;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Photo d'un objet, dans public/uploads/items (règles communes : App\Service\PhotoStorage).
 */
final class ItemPhotoStorage
{
    public const PUBLIC_PATH = '/uploads/items/';

    private readonly PhotoStorage $photos;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        Filesystem $filesystem = new Filesystem(),
    ) {
        $this->photos = new PhotoStorage($projectDir, $filesystem);
    }

    /**
     * Remplace la photo de l'objet et renvoie son URL publique.
     *
     * @throws \InvalidArgumentException si le contenu n'est pas une image JPEG valide
     */
    public function store(Item $item, string $dataUrl): string
    {
        $url = $this->photos->save(self::PUBLIC_PATH, (string) $item->getId(), $dataUrl);
        $this->remove($item);
        $item->setPhoto($url);

        return $url;
    }

    // Supprime le fichier de la photo actuelle (uniquement s'il a été envoyé par l'application)
    public function remove(Item $item): void
    {
        $this->photos->delete(self::PUBLIC_PATH, $item->getPhoto());
        $item->setPhoto(null);
    }
}
