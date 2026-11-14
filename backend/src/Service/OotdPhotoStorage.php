<?php

namespace App\Service;

use App\Entity\Ootd;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Photo d'un look du jour, dans public/uploads/ootds (règles communes : App\Service\PhotoStorage).
 * La photo de démo des fixtures (/uploads/demo/) n'est jamais supprimée.
 */
final class OotdPhotoStorage
{
    public const PUBLIC_PATH = '/uploads/ootds/';

    private readonly PhotoStorage $photos;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        Filesystem $filesystem = new Filesystem(),
    ) {
        $this->photos = new PhotoStorage($projectDir, $filesystem);
    }

    /**
     * Remplace la photo du look et renvoie son URL publique.
     *
     * @throws \InvalidArgumentException si le contenu n'est pas une image JPEG valide
     */
    public function store(Ootd $ootd, string $dataUrl): string
    {
        $url = $this->photos->save(self::PUBLIC_PATH, (string) $ootd->getId(), $dataUrl);
        $this->remove($ootd);
        $ootd->setPhoto($url);

        return $url;
    }

    public function remove(Ootd $ootd): void
    {
        $this->photos->delete(self::PUBLIC_PATH, $ootd->getPhoto());
        $ootd->setPhoto(null);
    }
}
