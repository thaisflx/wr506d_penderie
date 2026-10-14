<?php

namespace App\Service;

// Levée quand aucun modèle OpenRouter n'a pu analyser la photo
final class ScanFailedException extends \RuntimeException
{
    /**
     * @param list<string> $errors détail par modèle (pour les logs)
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
