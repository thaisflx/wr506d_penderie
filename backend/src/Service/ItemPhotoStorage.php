<?php

namespace App\Service;

use App\Entity\Item;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Enregistre la photo d'un objet dans public/uploads/items : nginx la sert ensuite directement.
 * Le front envoie une image déjà réduite (1024 px max) en JPEG base64, comme pour le scan.
 */
final class ItemPhotoStorage
{
    public const PUBLIC_PATH = '/uploads/items/';
    // Marge au-dessus des 1024 px envoyés par le front
    private const MAX_DIMENSION = 2048;

    private readonly string $directory;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
        $this->directory = $projectDir.'/public'.self::PUBLIC_PATH;
    }

    /**
     * Remplace la photo de l'objet et renvoie son URL publique.
     *
     * @throws \InvalidArgumentException si le contenu n'est pas une image JPEG valide
     */
    public function store(Item $item, string $dataUrl): string
    {
        $prefix = 'data:image/jpeg;base64,';
        $binary = str_starts_with($dataUrl, $prefix) ? base64_decode(substr($dataUrl, \strlen($prefix)), true) : false;
        // On vérifie le contenu réel du fichier, pas seulement l'en-tête annoncé
        $info = false !== $binary ? @getimagesizefromstring($binary) : false;
        if (false === $info || \IMAGETYPE_JPEG !== $info[2]) {
            throw new \InvalidArgumentException('Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).');
        }
        if (max($info[0], $info[1]) > self::MAX_DIMENSION) {
            throw new \InvalidArgumentException('Image trop grande : redimensionne-la à 1024 px avant l\'envoi.');
        }

        // Nom aléatoire : une nouvelle photo a une nouvelle URL, le cache du navigateur ne gêne pas
        $filename = \sprintf('%d-%s.jpg', $item->getId(), bin2hex(random_bytes(8)));
        $this->filesystem->dumpFile($this->directory.$filename, $binary);

        $this->remove($item);
        $item->setPhoto(self::PUBLIC_PATH.$filename);

        return $item->getPhoto();
    }

    // Supprime le fichier de la photo actuelle (uniquement s'il a été envoyé par l'application)
    public function remove(Item $item): void
    {
        $photo = $item->getPhoto();
        if (null !== $photo && str_starts_with($photo, self::PUBLIC_PATH)) {
            $this->filesystem->remove($this->directory.basename($photo));
        }
        $item->setPhoto(null);
    }
}
