# Audit qualité — PENDERIE

> Symfony 7.4 + API Platform + Vue 3 · Commandes : `make lint`, `make phpstan`, `make audit`, `make quality`

| Domaine | Outil | Avant | Après |
|---|---|---|---|
| Code PHP | PHPStan niveau 6 | ❌ 34 erreurs | ✅ 0 erreur |
| Config Symfony | `lint:container`, `lint:yaml`, `lint:twig` | ✅ | ✅ |
| `composer.json` | `composer validate` | ⚠️ 2 avertissements | ✅ |
| Dépendances PHP / JS | `composer audit` / `npm audit` | ✅ / ❌ 1 faille haute | ✅ / ✅ |
| Dépendances (Snyk) | Snyk Open Source | ✅ 0 faille | ✅ |
| Image Docker | Snyk Container | ❌ 3 C · 23 H · 8 M · 236 L | ✅ 0 C · 1 H · 1 M · 20 L (270 → 22) |
| Code source | Snyk Code | ⚠️ 1 H · 1 L | ✅ 2 faux positifs, justifiés |
| Accessibilité | Lighthouse, axe-core, clavier | 🟢 100 / 1 erreur | ✅ |
| Performance (prod) | Lighthouse | 🟢 96 | |
| Tests automatisés | PHPUnit, Cypress | ❌ aucun | ✅ 155 PHPUnit + 13 Cypress, 100 % sur le métier (voir [coverage.md](coverage.md)) |

---

## 1. PHPStan (niveau 6)

**État initial : 34 erreurs**

- 28 erreurs Doctrine : propriétés `?string` alors que la colonne est NOT NULL (schéma généré par `make:entity`)
- 4 conditions toujours vraies : code inutile, dont une à vérifier dans `ClothingScanner`
- 2 types manquants : tableau et générique non précisés

<details>
<summary>Sortie complète de PHPStan</summary>

```
 [ERROR] Found 34 errors
```
</details>

**Corrections**

| Erreurs | Correction |
|---|---|
| 28 × Doctrine | Option `doctrine.allowNullablePropertyForRequiredField: true` : la propriété est vide le temps qu'API Platform remplisse l'objet, la validation et la contrainte NOT NULL garantissent la donnée |
| `Item::$attributes` | `@var array<string, mixed>\|null` |
| `OwnerProcessor` | type générique ajouté, test `instanceof` inutile supprimé (le type est déjà garanti par `@implements`) |
| `ClothingScanner` l.85 | **pas un bug** : la boucle ne recommence que par `continue`, donc `$attempt <= 2` était toujours vrai → remplacé par `foreach ([1, 2] as $attempt)` |
| `ClothingScanner` l.208 | `(CategoryType::tryFrom(...) ?? CategoryType::Other)->value`, plus lisible et sans `?->` inutile |

**État final : `[OK] No errors`**

---

## 2. Qualité technique Symfony

| Vérification | Commande | Résultat initial | Résultat final |
|---|---|---|---|
| Conteneur de services | `lint:container` | ✅ OK | ✅ |
| Fichiers YAML | `lint:yaml config` | ✅ 17 fichiers valides | ✅ |
| Templates Twig | `lint:twig templates` | ✅ 1 fichier valide | ✅ |
| `composer.json` | `composer validate` | ⚠️ `name` et `description` manquants | ✅ corrigé |

**Analyse :** configuration saine. `name` et `description` ne sont obligatoires que pour publier un paquet, mais ont été ajoutés pour documenter le projet. Ce changement a désynchronisé `composer.lock`, resynchronisé avec `composer update --lock` (sans changer les versions des dépendances).

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

| Outil | Périmètre | Résultat initial | Résultat final |
|---|---|---|---|
| `composer audit` | dépendances PHP | ✅ aucune faille | ✅ |
| `npm audit` | dépendances JS | ❌ 1 faille haute (`source-map-js`) | ✅ corrigé |
| Snyk Open Source | `composer.lock`, `package.json` | ✅ 0 faille | ✅ |
| Snyk Container | `docker/php/Dockerfile` | ❌ 3 critiques, 23 hautes, 8 moyennes, 236 basses | ✅ 0 critique, 1 haute, 1 moyenne, 20 basses (270 → 22) |
| Snyk Code | code source PHP / JS | ⚠️ 1 haute, 1 basse | ✅ 2 faux positifs, justifiés |

**Analyse :**
- **Front :** `source-map-js` 1.2.1 présentait une faille de déni de service ([GHSA-68fv-2mgg-jv7q](https://github.com/advisories/GHSA-68fv-2mgg-jv7q)). Dépendance **indirecte** des outils de build (`vite > postcss`, compilateur Vue), pas du code envoyé au navigateur : risque faible, corrigée avec `npm audit fix` (1.2.2).
- **Snyk** confirme `composer audit` et `npm audit` : aucune faille dans les dépendances du projet. Il analyse aussi deux choses que ces outils ne voient pas :
  - **l'image Docker** : les 270 failles venaient des paquets système de l'image `php:8.3-fpm` (Debian), pas du code du projet. Elles touchent l'environnement d'exécution, donc elles restent à prendre au sérieux. Correction : passage à `php:8.3-fpm-alpine`, une image minimale avec beaucoup moins de paquets, et version de Composer fixée (`composer:2` au lieu de `latest`). **Résultat : 270 → 22 failles, 0 critique.**
    - La seule haute restante est **zlib 1.3.2-r0** (CVE-2026-85091, Out-of-bounds Write, CVSS 7.4, aucun exploit connu), corrigée dans 1.3.2-r1. Correction : `RUN apk upgrade --no-cache` juste après le `FROM` de `docker/php/Dockerfile`. Vérifié après `docker compose build backend` : `apk info -ev zlib` → `zlib-1.3.2-r1`.
    - Snyk analyse l'**image de base déclarée** dans le Dockerfile, pas l'image construite : l'alerte disparaîtra côté Snyk quand l'image officielle `php:8.3-fpm-alpine` sera reconstruite, mais notre image construite est déjà corrigée ;
  - **le code source (Snyk Code)** : 2 alertes, toutes deux des faux positifs.

| Gravité | Alerte | Emplacement | Analyse | Décision |
|---|---|---|---|---|
| Haute | Hardcoded Non-Cryptographic Secret | `frontend/src/api/client.js:3` (`TOKEN_KEY = 'penderie_token'`) | Faux positif : c'est le **nom** de la clé du `localStorage`, pas un secret. Remarque liée : le JWT y est stocké, ce qui l'exposerait en cas de faille XSS. Amélioration possible : un cookie `HttpOnly` | Risque accepté : Vue échappe le HTML par défaut, et le front n'utilise ni `v-html` ni `innerHTML` (vérifié par `grep`) |
| Basse | Use of Hardcoded Credentials | `backend/src/DataFixtures/AppFixtures.php:165` (`createUser()`, numérotation de `main`) | Mot de passe `password` des comptes de démo | Risque accepté : `DoctrineFixturesBundle` n'est activé qu'en `dev` et `test` (`config/bundles.php`), les fixtures ne peuvent pas être chargées en production |

- **Secrets :** `.env` est versionné avec `JWT_PASSPHRASE`. Les clés JWT et la clé OpenRouter ne sont pas commitées, mais en production les secrets devront passer par `.env.local`.
- **Bonne pratique :** lancer `make audit` régulièrement, de nouvelles failles sont publiées tous les jours.

<details>
<summary>Sortie initiale de npm audit</summary>

```
source-map-js  1.0.0 - 1.2.1
Severity: high
source-map-js allows event-loop denial of service through indexed source-map section offsets - https://github.com/advisories/GHSA-68fv-2mgg-jv7q
fix available via `npm audit fix`

1 high severity vulnerability
```
</details>

---

## 4. Inspection manuelle du code

**Points forts :** les formulaires des 5 niveaux de la hiérarchie sont générés par un seul composant (`NodeForm`) à partir d'une configuration (`hierarchy.js`), sans duplication ; chaque vue a un rôle clair : `HomeView` (logement et pièces), `RoomView` (rangements, catégories et objets d'une pièce, via `StorageAccordion`), `ItemView` (fiche objet) ; le client API centralise le jeton, les erreurs et l'expiration de session ; côté back, chaque classe a une responsabilité claire (`OwnerProcessor`, `CurrentOwnerExtension`, `UserChecker`).

**Dette technique :**

| Constat | Risque | Action |
|---|---|---|
| `Place::setAddress(string)` refuse `null` alors que la colonne est nullable et que le formulaire envoie `null` si l'adresse est vide | Bug probable | `?string` (idem `User::setBirthDate`) |
| La règle « qui est le propriétaire ? » est écrite 5 fois (4 `securityPostDenormalize` + `CurrentOwnerExtension`), et `Item` a deux sources de propriétaire (`owner` et la chaîne catégorie → logement) | Moyen | Centraliser dans les Voters (étape suivante) |
| `Place` n'a pas de règle `security` sur `Patch`/`Delete` : la protection repose uniquement sur le filtre Doctrine | Faible | Règles explicites avec les Voters |

---

## 5. Accessibilité, performance et SEO — Lighthouse

**Outil :** Lighthouse (Chrome DevTools), mode Navigation, appareil Mobile

### Mesure 1 : serveur de développement (`npm run dev`, port 5173)

| Page | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---|---|---|
| Connexion (`/login`) | 🟠 55 | 🟢 100 | 🟢 100 | 🟠 82 |
| Accueil (`/`) | 🟠 56 | 🟢 100 | 🟢 100 | 🟠 82 |

**Analyse :**
- **Accessibilité et bonnes pratiques à 100** : labels de formulaires présents, contrastes suffisants, `lang="fr"`.
- **SEO à 82** : `<meta name="description">` et `robots.txt` manquants → ajoutés (description de l'application, `robots.txt` qui exclut `/api/`).
- **Performance à 55** : **pas représentatif**. Le serveur de développement de Vite sert le code non minifié, un fichier par module. Mesure refaite en production.

### Mesure 2 : version de production (`vite preview`, port 4173)

| Page | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---|---|---|
| Connexion | 🟢 96 | 🟢 100 | 🟢 100 | 🟢 91 |

### Compléments : axe-core et clavier

| Critère | Constat | État |
|---|---|---|
| axe-core (WCAG 2.1 AA) | Connexion : 0 erreur. Accueil : titres `h1` → `h3` (niveau sauté) | ✅ Front actuel : plus aucun `h3`. L'accueil (`HomeView`) n'avait pas de `h1` → `h1` « Accueil » ajouté, masqué visuellement (`visually-hidden`). ⚠️ `ScanView` n'a toujours pas de titre (« Scanner » est un `span`) |
| Navigation clavier | Tout est atteignable avec Tab, dans un ordre logique | ✅ |
| Focus visible | Champs et boutons : contour de 2 px. Liens : contour par défaut, plus discret | ⚠️ → `a:focus-visible` ajouté |
| Messages d'erreur | En français, annoncés (`role="alert"`), sans révéler si l'email existe | ✅ |

**Limite :** un score de 100 sur Lighthouse ne garantit pas une accessibilité complète : les outils automatiques ne vérifient qu'une partie des critères.

---

## 6. Écoconception

Page de connexion en production : **≈ 46 Ko en 4 requêtes** (JS 42,7 Ko compressé, CSS 2,1 Ko), premier affichage en 1,1 s, aucune police externe ni image.

**Améliorations :** suppression de `public/icons.svg` (reste du template Vite, jamais utilisé) ; favicon Vite par défaut à remplacer par le logo ; à prévoir pour le scan : photos compressées (WebP) et chargées en `loading="lazy"`.

---

## 7. Expérience utilisateur (UX)

- ✅ États de chargement, bouton désactivé pendant l'envoi
- ✅ Fil d'Ariane (logement › pièce › rangement) sur la fiche objet (`ItemView`) seulement
- ✅ Suppression en deux temps : le formulaire avertit de ce qui sera supprimé (« Tous ses rangements et tous les objets qu'ils contiennent seront supprimés. ») et demande « Oui, supprimer »
- ✅ Erreurs de validation affichées sous le champ concerné, message d'invitation quand une liste est vide
- ⚠️ Pas de confirmation après une création ou une modification
- ✅ Couleurs de la DA appliquées (bordeaux `#71103c`, cyan, glauque dans `style.css`)

---

## 8. Testabilité

- **Tests** : `backend/tests/` (Unit, Integration, Functional) et `frontend/cypress/e2e/`.
- **Résultat** : 155 tests PHPUnit et 13 tests Cypress, 100 % de couverture sur les classes métier. Détail dans [coverage.md](coverage.md), commandes et captures dans [ci.md](ci.md).
- **Anomalies trouvées** : un `catch` « erreur réseau » jamais exécuté dans `ClothingScanner`, et le message « Ce compte est suspendu. » masqué dans `LoginView`. Les deux sont corrigées.
- **Données** : les fixtures de test (groupe `test` : camille, sophie, theo…) sont séparées des fixtures de démo (groupe `dev` : alice, leo, bob…), dans une base dédiée `penderie_db_test`.

---

## 9. Plan d'action

| Priorité | Action | Statut |
|---|---|---|
| ✅ | PHPStan niveau 6 : 34 → 0 erreur | Fait |
| ✅ | `npm audit fix`, `composer.json` complété | Fait |
| ✅ | Makefile (`lint`, `phpstan`, `audit`, `quality`, puis `test`, `coverage`, `e2e`) | Fait |
| ✅ | `:focus-visible` sur tous les éléments, plus de niveau de titre sauté, `h1` sur l'accueil | Fait |
| ✅ | Meta description, `robots.txt`, `public/icons.svg` supprimé, nom « Penderie » partout | Fait |
| ✅ | Image Docker passée en Alpine, Composer fixé en version 2 (Snyk Container) | Fait |
| ✅ | zlib mis à jour (`apk upgrade`) | Fait |
| ✅ | Snyk Code : 2 faux positifs analysés et justifiés | Fait |
| ✅ | Tests PHPUnit et e2e Cypress | Fait |
| 🔴 | `setAddress(?string)`, `setBirthDate(?…)` | À faire |
| 🟠 | Centraliser les droits dans des Voters | Prochaine étape |
| 🟠 | Tests des droits quand les Voters seront écrits | À faire |
| 🟢 | Notification de succès, favicon Penderie (celui de Vite est encore en place) | À faire |
| 🟢 | Jeton JWT en cookie `HttpOnly` | Piste |
