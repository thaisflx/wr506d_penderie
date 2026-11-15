<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Stockage commun des photos (objets, looks du jour) dans public/ : nginx les sert ensuite directement.
 * Le front envoie une image déjà réduite (1024 px max) en JPEG base64.
 */
final class PhotoStorage
{
    // Marge au-dessus des 1024 px envoyés par le front
    private const MAX_DIMENSION = 2048;

    public function __construct(
        private readonly string $projectDir,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Vérifie le contenu, enregistre le fichier sous un nom aléatoire et renvoie son URL publique.
     *
     * @param string $publicPath dossier public, ex. /uploads/items/
     * @param string $prefix     début du nom de fichier (identifiant de l'objet ou du look)
     *
     * @throws \InvalidArgumentException si le contenu n'est pas une image JPEG valide
     */
    public function save(string $publicPath, string $prefix, string $dataUrl): string
    {
        $mimePrefix = 'data:image/jpeg;base64,';
        $binary = str_starts_with($dataUrl, $mimePrefix) ? base64_decode(substr($dataUrl, \strlen($mimePrefix)), true) : false;
        // On vérifie le contenu réel du fichier, pas seulement l'en-tête annoncé
        $info = false !== $binary ? @getimagesizefromstring($binary) : false;
        if (false === $info || \IMAGETYPE_JPEG !== $info[2]) {
            throw new \InvalidArgumentException('Envoie une image JPEG encodée en base64 (data:image/jpeg;base64,...).');
        }
        if (max($info[0], $info[1]) > self::MAX_DIMENSION) {
            throw new \InvalidArgumentException('Image trop grande : redimensionne-la à 1024 px avant l\'envoi.');
        }

        // Nom aléatoire : une nouvelle photo a une nouvelle URL, le cache du navigateur ne gêne pas
        $filename = \sprintf('%s-%s.jpg', $prefix, bin2hex(random_bytes(8)));
        $this->filesystem->dumpFile($this->directory($publicPath).$filename, $binary);

        return $publicPath.$filename;
    }

    /**
     * Supprime le fichier d'une photo, uniquement s'il a été envoyé dans ce dossier par l'application
     * (une URL externe ou un chemin forgé avec ../ ne touche à rien d'autre).
     */
    public function delete(string $publicPath, ?string $url): void
    {
        if (null !== $url && str_starts_with($url, $publicPath)) {
            $this->filesystem->remove($this->directory($publicPath).basename($url));
        }
    }

    private function directory(string $publicPath): string
    {
        return $this->projectDir.'/public'.$publicPath;
    }
}
