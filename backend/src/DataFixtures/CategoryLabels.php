<?php

namespace App\DataFixtures;

/**
 * Catégories proposées par l'application dans les fixtures : nom français => clé de traduction (Category.labelKey).
 * Le front affiche la traduction de la clé dans la langue choisie ; le français est exactement le nom ci-dessous
 * (docs/decisions.md, « Internationalisation »). Une catégorie créée par un utilisateur n'a pas de clé.
 */
final class CategoryLabels
{
    public const KEYS = [
        'Baskets' => 'category.sneakers',
        'Bijoux' => 'category.jewellery',
        'Chaussures' => 'category.shoes',
        'Décorations' => 'category.decorations',
        'Divers' => 'category.misc',
        'Électroportatif' => 'category.power_tools',
        'Hauts' => 'category.tops',
        'Pantalons' => 'category.trousers',
        'Papiers' => 'category.papers',
        'Pièces de valeur' => 'category.valuables',
        'Pop' => 'category.pop',
        'Rock' => 'category.rock',
        'Sweats' => 'category.sweatshirts',
        'T-shirts' => 'category.tshirts',
        'Toilette' => 'category.toiletries',
        'Vêtements' => 'category.clothes',
        'Vêtements d\'hiver' => 'category.winter_clothes',
    ];

    // Clé de la catégorie proposée de ce nom ; null pour un nom qui n'est pas dans la liste
    public static function keyFor(string $name): ?string
    {
        return self::KEYS[$name] ?? null;
    }
}
