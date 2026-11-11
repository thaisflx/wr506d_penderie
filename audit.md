# Audit qualité — PENDERIE

> Symfony 7.4 + API Platform + Vue 3 · Commandes : `make lint`, `make phpstan`, `make audit`, `make quality`

| Domaine | Outil | Avant | Après |
|---|---|---|---|
| Code PHP | PHPStan niveau 6 | ❌ 34 erreurs | ✅ 0 erreur |
| Config Symfony | `lint:container`, `lint:yaml`, `lint:twig` | ✅ | ✅ |
| `composer.json` | `composer validate` | ⚠️ 2 avertissements | ✅ |
| Dépendances PHP / JS | `composer audit` / `npm audit` | ✅ / ❌ 1 faille haute, puis ❌ 18 avis Symfony (11/2026) | ✅ 0 avis / ✅ |
| Dépendances (Snyk) | Snyk Open Source | ✅ 0 faille | ✅ |
| Image Docker | Snyk Container | ❌ 3 C · 23 H · 8 M · 236 L | ✅ 0 C · 1 H · 1 M · 20 L (270 → 22) |
| Code source | Snyk Code | ⚠️ 1 H · 1 L | ✅ 2 faux positifs, justifiés |
| Accessibilité | Lighthouse, axe-core, clavier | 🟢 100 / 1 erreur | ✅ |
| Performance (prod) | Lighthouse | 🟢 96 | |
| Tests automatisés | PHPUnit, Cypress | ❌ aucun | ✅ 253 PHPUnit + 24 Cypress, 100 % sur le métier (voir [coverage.md](coverage.md)) |
| Contrastes (refonte Lendly) | Calcul WCAG 2.1 | | ✅ tous les textes ≥ 4,5:1, logo exempté |

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
| `composer audit` | dépendances PHP | ✅ aucune faille | ✅ 0 avis (CVE-2026-109449, puis 18 avis Symfony de novembre 2026, publiés ensuite et corrigés) |
| `npm audit` | dépendances JS | ❌ 1 faille haute (`source-map-js`) | ✅ corrigé |
| Snyk Open Source | `composer.lock`, `package.json` | ✅ 0 faille | ✅ |
| Snyk Container | `docker/php/Dockerfile` | ❌ 3 critiques, 23 hautes, 8 moyennes, 236 basses | ✅ 0 critique, 1 haute, 1 moyenne, 20 basses (270 → 22) |
| Snyk Code | code source PHP / JS | ⚠️ 1 haute, 1 basse | ✅ 2 faux positifs, justifiés |

**Analyse :**
- **Back :** un nouvel avis, publié le 14/10/2026, touche `symfony/expression-language` 7.4.18 : CVE-2026-109449, une expression imbriquée sans limite peut faire planter le processus. Risque faible : les expressions du projet (`securityPostDenormalize`) sont écrites dans le code, jamais fournies par l'utilisateur. Corrigée avec `composer update symfony/expression-language` (7.4.21) : `composer audit` est de nouveau ✅.
- **Back, novembre 2026 : 18 avis Symfony.** `composer audit` signalait 18 avis sur 11 paquets Symfony 7.4, tous corrigés en **7.4.23**. La base d'avis ne publie pas de score de gravité (`severity` vide) : la colonne « Exposition » donne notre analyse pour ce projet.

  | Paquet | Installé | Corrigé | Avis | Exposition du projet |
  |---|---|---|---|---|
  | `symfony/config` | 7.4.17 | 7.4.23 | CVE-2026-123824 : XXE (lecture de fichier local) dans `XmlUtils::parse()` | Faible : aucune configuration XML |
  | `symfony/filesystem` | 7.4.18 | 7.4.23 | PKSA-g499-cm4q-v35y : un préfixe avant `://` fait sortir un chemin de son dossier | Faible : chemins construits par le code (`ItemPhotoStorage`) |
  | `symfony/framework-bundle` | 7.4.19 | 7.4.23 | PKSA-jvt1-tggb-8w72 : raccourci `private_ranges` des proxys de confiance trop large | Nulle : pas de `trusted_proxies` |
  | `symfony/http-client` | 7.4.20 | 7.4.23 | CVE-2026-121971 : `ScopingHttpClient` envoie ses identifiants à d'autres origines | Faible : client simple vers OpenRouter |
  | `symfony/http-client` | 7.4.20 | 7.4.23 | CVE-2026-123119 : `NoPrivateNetworkHttpClient`, DNS rebinding (correctif incomplet de CVE-2024-50342) | Nulle : non utilisé |
  | `symfony/http-foundation` | 7.4.19 | 7.4.23 | CVE-2026-118514 : requêtes `HEAD` qui contournent l'option `methods` d'`access_control` | Moyenne : règles `access_control` présentes |
  | `symfony/http-foundation` | 7.4.19 | 7.4.23 | PKSA-pr6q-2w5s-kzzf : règles de chemin placées avant PHP contournables | Faible : nginx transmet tout à `index.php` |
  | `symfony/http-foundation` | 7.4.19 | 7.4.23 | PKSA-nxjs-s6hn-vb6m : même problème `private_ranges` | Nulle |
  | `symfony/http-foundation` | 7.4.19 | 7.4.23 | PKSA-b2gg-vk1g-6d5q : URL générées vers un hôte choisi par le client | Faible : peu d'URL absolues générées |
  | `symfony/http-kernel` | 7.4.19 | 7.4.23 | CVE-2026-123339 : fragments inline et en-têtes `Forwarded` non fiables | Nulle : pas de fragments |
  | `symfony/routing` | 7.4.18 | 7.4.23 | CVE-2026-118516 : un paramètre d'hôte peut avaler le début du chemin | Nulle : pas de route à hôte variable |
  | `symfony/security-http` | 7.4.20 | 7.4.23 | CVE-2026-118513 : `X509Authenticator`, lecture des DN (correctif incomplet) | Nulle : authentification JWT |
  | `symfony/security-http` | 7.4.20 | 7.4.23 | CVE-2026-123746 : les connexions programmatiques sautent `checkPostAuth()` | Faible : pas de connexion programmatique |
  | `symfony/serializer` | 7.4.20 | 7.4.23 | CVE-2026-121623 : `DataUriNormalizer` ouvre une data URI à schéma non minuscule comme fichier local | Moyenne : le serializer d'API Platform traite les données des clients |
  | `symfony/serializer` | 7.4.20 | 7.4.23 | CVE-2026-122053 : chemin de propriété pointé qui contourne `#[Ignore]` | Moyenne : même raison |
  | `symfony/serializer` | 7.4.20 | 7.4.23 | CVE-2026-118515 : `UploadedFile` construit à partir des données de la requête | Moyenne : même raison |
  | `symfony/validator` | 7.4.19 | 7.4.23 | CVE-2026-123125 : contrainte `Ip` public/privé contournable (IPv6 mappée) | Nulle : contrainte non utilisée |
  | `symfony/yaml` | 7.4.18 | 7.4.23 | CVE-2026-123792 : temps d'analyse exponentiel du parseur YAML | Faible : seuls nos fichiers de configuration sont lus |

  **Correction :** `composer update "symfony/*" --with-dependencies`, sans changer de version majeure ni de mineure (contraintes `7.4.*` et `extra.symfony.require` inchangées). 52 paquets mis à jour en version corrective : les composants Symfony en 7.4.21 à 7.4.23, les contrats et polyfills Symfony, et 5 dépendances (`doctrine/deprecations`, `doctrine/inflector`, `doctrine/lexer`, `doctrine/persistence`, `masterminds/html5`). Composer avait resserré la contrainte de `symfony/flex` (`^2` → `^2.12`) : remise à `^2`, `composer.json` est inchangé.

  **Résultat :** `composer audit` → 0 avis ; `composer validate`, `make lint`, `make phpstan` (0 erreur), `make test` (253 tests) et `make e2e` (24 tests) passent.
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
| axe-core (WCAG 2.1 AA) | Connexion : 0 erreur. Accueil : titres `h1` → `h3` (niveau sauté) | ✅ Front actuel : plus aucun `h3`. L'accueil (`HomeView`) n'avait pas de `h1` → `h1` ajouté ; depuis la refonte, c'est le titre visible « Bonjour {pseudo} ». Le scanner (`ScanView`) : « Scanner » passé d'un `span` à un `h1`, même rendu (style calculé et position identiques) |
| Navigation clavier | Tout est atteignable avec Tab, dans un ordre logique | ✅ |
| Focus visible | Champs et boutons : contour de 2 px. Liens : contour par défaut, plus discret | ⚠️ → `a:focus-visible` ajouté |
| Messages d'erreur | En français, annoncés (`role="alert"`), sans révéler si l'email existe | ✅ |

**Limite :** un score de 100 sur Lighthouse ne garantit pas une accessibilité complète : les outils automatiques ne vérifient qu'une partie des critères.

---

## 6. Écoconception

Page de connexion en production, mesurée avant la refonte Lendly : **≈ 46 Ko en 4 requêtes** (JS 42,7 Ko compressé, CSS 2,1 Ko), premier affichage en 1,1 s, aucune police externe ni image. La refonte ajoute la police des titres (voir ci-dessous).

**Améliorations :** suppression de `public/icons.svg` (reste du template Vite, jamais utilisé) ; favicon Vite par défaut (9,5 Ko) remplacé par le « Y. » du logo en tracés SVG (1,2 Ko) ; à prévoir pour le scan : photos compressées (WebP) et chargées en `loading="lazy"`.

### Refonte visuelle Lendly (maquette v2)

Référence : [`docs/maquette-v2/`](docs/maquette-v2/README.md) ; décisions dans [decisions.md](docs/decisions.md) (« Refonte visuelle »).

| Point | Constat | État |
|---|---|---|
| Police des titres | **Mono Scribble, version démo** (TimelessType, « All Rights Reserved »), sans chiffres : `unicode-range` les fait passer en Bricolage Grotesque 800 (Google Fonts). Usage limité au projet scolaire : la licence n'autorise ni l'usage commercial ni la modification du fichier (pas de sous-ensemble ni de conversion). | ⚠️ licence à acheter avant toute mise en ligne publique |
| Poids de la police | `MonoScribble.ttf` : **96,6 Ko**, servi **sans compression** par le serveur Vite en développement (nginx ne sert que l'API) ; **≈ 29,7 Ko en gzip**. `font-display: block` pour ne pas afficher un instant la police de secours. | ⚠️ voir plan d'action |
| Contrastes (WCAG 2.1 AA) | Calculés pour chaque couple texte / fond : blanc sur violet foncé `#5E6CA8` 5,0:1 ; texte indicatif `#7D6F80` sur blanc 4,7:1 ; texte secondaire sur rose pâle 5,0:1 ; tous les autres couples > 6:1. | ✅ |
| Logo | Texte en Mono Scribble, `role="img"` et `aria-label="Lendly"`. Le rose `#EEB4B3` (1,7:1) est accepté : un logo est exempté du critère de contraste (WCAG 1.4.3). | ✅ exempté |
| Éléments à venir | Entrée « Looks » de la barre, « Poster mon look », « Prêter / Vendre à un ami » : affichés désactivés (`aria-disabled`, « Bientôt ») tant que l'API n'existe pas. | ✅ |
| Décor | Étoiles décoratives en `aria-hidden`, sans `pointer-events` ; pas de ligne violette (choix visuel). | ✅ |
| Photo de démo | `backend/public/uploads/demo/t-shirt-blanc.jpg` (14 Ko), illustration générée par script pour les fixtures `dev`. | ✅ |

---

## 7. Expérience utilisateur (UX)

- ✅ États de chargement, bouton désactivé pendant l'envoi
- ✅ Fil d'Ariane sur la page pièce (« Maison / Dressing ») et sur la fiche objet (« Maison › Dressing › Penderie »)
- ✅ Suppression en deux temps : le formulaire avertit de ce qui sera supprimé (« Tous ses rangements et tous les objets qu'ils contiennent seront supprimés. ») et demande « Oui, supprimer »
- ✅ Erreurs de validation affichées sous le champ concerné, message d'invitation quand une liste est vide
- ⚠️ Pas de confirmation après une création ou une modification
- ✅ Charte Lendly (maquette v2) appliquée à tous les écrans : variables de couleur dans `style.css`, pilules, cartes festonnées, forme fleur, barre de navigation flottante
- ✅ Statut d'un objet modifiable en un clic depuis la fiche (pilules d'état, testé par `item.cy.js`)

---

## 8. Testabilité

- **Tests** : `backend/tests/` (Unit, Integration, Functional) et `frontend/cypress/e2e/`.
- **Résultat** : 253 tests PHPUnit et 24 tests Cypress, 100 % de couverture sur les classes métier (19 classes, dont le volet social). Détail dans [coverage.md](coverage.md), commandes et captures dans [ci.md](ci.md).
- **Anomalies trouvées** : un `catch` « erreur réseau » jamais exécuté dans `ClothingScanner`, et le message « Ce compte est suspendu. » masqué dans `LoginView`. Les deux sont corrigées.
- **Données** : les fixtures de test (groupe `test` : camille, sophie, theo…) sont séparées des fixtures de démo (groupe `dev` : alice, leo, bob…), dans une base dédiée `penderie_db_test`.

---

## 9. Plan d'action

| Priorité | Action | Statut |
|---|---|---|
| ✅ | PHPStan niveau 6 : 34 → 0 erreur | Fait |
| ✅ | `npm audit fix`, `composer.json` complété | Fait |
| ✅ | Makefile (`lint`, `phpstan`, `audit`, `quality`, puis `test`, `coverage`, `e2e`) | Fait |
| ✅ | `:focus-visible` sur tous les éléments, plus de niveau de titre sauté, `h1` sur l'accueil et le scanner | Fait |
| ✅ | Meta description, `robots.txt`, `public/icons.svg` supprimé, nom de l'application Lendly harmonisé | Fait |
| ✅ | Refonte visuelle Lendly d'après la maquette v2 : police, palette, composants, tous les écrans, favicon « Y. » | Fait |
| ✅ | Image Docker passée en Alpine, Composer fixé en version 2 (Snyk Container) | Fait |
| ✅ | zlib mis à jour (`apk upgrade`) | Fait |
| ✅ | Snyk Code : 2 faux positifs analysés et justifiés | Fait |
| ✅ | Tests PHPUnit et e2e Cypress | Fait |
| ✅ | Volet social, lot 1 : blocage, demandes d'abonnement et d'ami proche, profil public, recherche, page Amis ([decisions.md](docs/decisions.md)) | Fait |
| 🔴 | `setAddress(?string)`, `setBirthDate(?…)` | À faire |
| 🟠 | Centraliser les droits dans des Voters (`OotdVoter`, premier Voter, au lot 2 du volet social) | Prochaine étape |
| 🟠 | Tests des droits quand les Voters seront écrits (la matrice de `RelationshipChecker` est déjà testée) | À faire |
| 🟠 | Lot 2 du volet social : écran « Looks » (`/feed`, entrée de la barre aujourd'hui désactivée) et « Poster mon look » | Prochaine étape |
| 🟢 | Notification de succès | À faire |
| 🟢 | Police des titres en woff2 sous-ensemblé (≈ 20 Ko au lieu de 96 Ko) après achat de la licence | Piste |
| 🟢 | Invitation d'amis par lien (« comme Duolingo », brief) : carte « Invite tes proches » de la maquette, non affichée faute d'API | Piste |
| 🟢 | Jeton JWT en cookie `HttpOnly` | Piste |
