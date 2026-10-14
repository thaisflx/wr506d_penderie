## 1. PHPStan (niveau 6)

**État initial : 34 erreurs**

- 28 erreurs Doctrine : propriétés `?string` alors que la colonne est NOT NULL (schéma généré par `make:entity`)
- 4 conditions toujours vraies : code inutile, dont une à vérifier dans `ClothingScanner` (bug possible)
- 2 types manquants : tableau et générique non précisés

<details>
<summary>Sortie complète de PHPStan</summary>

```                      
 [ERROR] Found 34 errors  
```
</details>

## 2. Qualité technique Symfony

**Commande :** `make lint`

| Vérification | Commande | Résultat initial | Résultat final |
|---|---|---|---|
| Conteneur de services | `lint:container` | ✅ OK | ✅ OK |
| Fichiers YAML | `lint:yaml config` | ✅ 17 fichiers valides | ✅ |
| Templates Twig | `lint:twig templates` | ✅ 1 fichier valide | ✅ |
| `composer.json` | `composer validate` | ⚠️ `name` et `description` manquants | ✅ corrigé |

**Analyse :** la configuration Symfony est saine : les services sont bien injectés et les fichiers YAML et Twig sont valides. Le seul avertissement concernait `composer.json` : les champs `name` et `description` ne sont obligatoires que pour publier un paquet, mais ils ont été ajoutés pour que le projet soit bien documenté et pour obtenir une validation sans avertissement.

<details>
<summary>Sortie initiale</summary>

```
[OK] The container was linted successfully: all services are injected with
     values that are compatible with their type declarations.
[OK] All 17 YAML files contain valid syntax.
[OK] All 1 Twig files contain valid syntax.

./composer.json is valid for simple usage with Composer but has
strict errors that make it unable to be published as a package
# Publish errors
- name : The property name is required
- description : The property description is required
```

</details>

---

## 3. Sécurité des dépendances

**Commande :** `make audit`

| Outil | Périmètre | Résultat initial | Résultat final |
|---|---|---|---|
| `composer audit` | dépendances PHP (back) | ✅ aucune faille | ✅ |
| `npm audit` | dépendances JS (front) | ❌ 1 faille haute (`source-map-js`) | ✅ corrigé |
| Snyk | dépôt GitHub | _(à compléter)_ | |

**Analyse :**
- **Back** : aucune vulnérabilité connue dans les dépendances Symfony.
- **Front** : `source-map-js` (versions 1.0.0 à 1.2.1) présentait une faille de déni de service ([GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q)). C'est une dépendance **indirecte** utilisée par les outils de build, et non par le code envoyé au navigateur, donc le risque réel en production est faible. Elle a quand même été corrigée avec `npm audit fix`, sans changement majeur de version.
- **Bonne pratique retenue** : lancer `make audit` régulièrement, car de nouvelles failles sont publiées tous les jours, même sur un projet qui ne change pas.

<details>
<summary>Sortie initiale de npm audit</summary>

```
# npm audit report

source-map-js  1.0.0 - 1.2.1
Severity: high
source-map-js allows event-loop denial of service through indexed source-map section offsets - https://github.com/advisories/GHSA-68fv-2mgg-jv7q
fix available via `npm audit fix`
node_modules/source-map-js

1 high severity vulnerability
```

</details>

## 5. Accessibilité, performance et SEO — Lighthouse

**Outil :** Lighthouse (Chrome DevTools), mode Navigation, appareil Mobile

### Mesure 1 : serveur de développement (`npm run dev`, port 5173)

| Page | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---|---|---|
| Connexion (`/login`) | 🟠 55 | 🟢 100 | 🟢 100 | 🟠 82 |
| Accueil (`/`) | 🟠 56 | 🟢 100 | 🟢 100 | 🟠 82 |

**Analyse :**
- **Accessibilité et bonnes pratiques à 100** : labels de formulaires présents, contrastes suffisants, attribut `lang="fr"` sur la page.
- **SEO à 82** : il manquait la balise `<meta name="description">` dans `index.html`. Elle a été ajoutée.
- **Performance à 55** : ce score n'est **pas représentatif**. Le serveur de développement de Vite sert le code non minifié, un fichier par module, avec le rechargement à chaud. La mesure a donc été refaite sur la version de production (`npm run build` puis `vite preview`).

### Mesure 2 : version de production (`vite preview`, port 4173)

| Page | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---|---|---|
| Connexion | 🟢 96 | 🟢 100 | 🟢 100 | 🟢 91 |
| Accueil | | | | |

**Limite :** un score de 100 en accessibilité sur Lighthouse ne garantit pas une accessibilité complète. L'outil ne vérifie qu'environ un tiers des critères. Il est complété par WAVE et un test de navigation au clavier.