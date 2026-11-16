# Campagne de test et couverture

Le dépôt est séparé en deux applications :

- **`backend/`** : API Symfony 7.4 + API Platform (PHP 8.3, PostgreSQL) ;
- **`frontend/`** : application Vue 3 + Vite.

Les deux tournent dans Docker (conteneurs `penderie_backend`, `penderie_frontend`, `penderie_web`, `penderie_db`). Les commandes sont décrites dans [ci.md](ci.md).

## Où sont les tests

| Emplacement | Contenu | Outil |
|---|---|---|
| `backend/tests/Unit/` | Classes métier testées seules, sans base de données | PHPUnit 12 (`TestCase`) |
| `backend/tests/Integration/` | Requêtes Doctrine réelles sur la base de test | PHPUnit 12 (`KernelTestCase`) |
| `backend/tests/Functional/` | Requêtes HTTP sur l'API, du jeton JWT à la réponse JSON | PHPUnit 12 (`WebTestCase`) |
| `backend/tests/Support/` | Utilitaire commun (`Images` : en-têtes JPEG/PNG minimaux) | — |
| `frontend/cypress/e2e/` | Parcours utilisateur dans le navigateur | Cypress 15.21.1 |
| `backend/src/DataFixtures/Test/` | Jeu de données du groupe `test`, décrit dans [docs/campagne-test.md](docs/campagne-test.md) | Doctrine Fixtures |

Tous les tests s'appuient sur les mêmes comptes et les mêmes données : Camille (propriétaire), Sacha et Lou (comptes enfants), Sophie (amie proche), Théo (abonné), Nina (demande en attente), Marc (abonné, que les tests bloquent), Zoé (aucune relation), un compte suspendu et un administrateur. Depuis le lot 2, Camille a aussi deux looks du jour publiés et un brouillon, et Sophie un look publié.

## Périmètre de couverture

La couverture est mesurée avec PCOV sur le **code métier écrit pour l'application**. Le périmètre est défini dans `<source>` de [backend/phpunit.dist.xml](backend/phpunit.dist.xml) :

| Inclus | Rôle |
|---|---|
| `src/Service` | Scan IA des vêtements (`ClothingScanner`), `ScanFailedException`, droits entre deux personnes (`RelationshipChecker`) ; stockage commun des photos (`PhotoStorage`) et ses deux usages (`ItemPhotoStorage`, `OotdPhotoStorage`) ; objet montrable dans un look (`ItemExposure`, lot 2) ; KPI du back office (`AdminStats`) ; « Mon espace » (`MySpace`) |
| `src/Security` | `UserChecker` : refus des comptes suspendus ; `OotdVoter` (lot 2) : lire un look, le modifier ou le supprimer |
| `src/State` | `OwnerProcessor` : le créateur d'un logement ou d'un objet en devient propriétaire ; `PublicProfileProvider`, `RelationshipProcessor`, `BlockProcessor` (volet social) ; `OotdProcessor` (lot 2) : le propriétaire d'un look est toujours l'utilisateur connecté |
| `src/Doctrine` | `CurrentOwnerExtension` : chaque utilisateur ne voit que son contenu ; `PublicUserExtension`, `RelationshipExtension`, `BlockExtension` (qui peut voir qui, quelle relation, quel blocage) ; `OotdVisibilityExtension` (lot 2) : looks visibles dans le fil, sur un profil et en lecture |
| `src/EventListener` | `ItemPhotoCleaner`, `OotdPhotoCleaner` : suppression du fichier photo avec l'objet ou le look |
| `src/EventSubscriber` | `UserLocaleSubscriber` (i18n) : langue du compte appliquée à la requête ; `LastLoginSubscriber` (back office) : date de dernière connexion |
| `src/Controller` | `/api/me` (et `PATCH /api/me`, i18n), `/api/me/stats`, `/api/me/export` (« Mon espace »), `/api/scan`, `/api/items/{id}/photo`, `/api/ootds/{id}/photo`, `PhotoRequestTrait` ; `Controller/Admin` : connexion, tableau de bord et CRUD du back office (EasyAdmin) |
| `src/Validator` | `ShareableItemsValidator` (lot 2) : objets d'un look, seulement les miens, jamais privés ni perso |
| `src/Serializer` | `OotdNormalizer` (lot 2) : retire d'un look, pour les autres, un objet devenu privé ou perso après la publication |
| `src/Repository/RelationshipRepository.php`, `src/Repository/BlockRepository.php`, `src/Repository/ItemRepository.php` | Seuls repositories qui contiennent des requêtes écrites à la main (`countReceived`, `hasAccepted`, `deleteBetween`, `existsBetween` ; règle « partageable » en requête pour le back office) |

| Exclu | Pourquoi |
|---|---|
| `src/Entity` | Mapping Doctrine et accesseurs générés, sans logique. Les méthodes qui contiennent une règle métier ont quand même leurs tests : `User::isMinor`, `ShareLink::isActive` (unitaires), la validation d'un `Ootd` (photo obligatoire pour publier, date future refusée : `OotdTest`) et **`Item::isShareable`** (lot 2). Cette dernière porte la règle « partageable », exposée au front : elle n'est donc pas mesurée par le 100 %, mais elle est testée par la matrice de `ItemExposureTest` (qui y délègue), par `OotdApiTest` et par des mutations (voir plus bas). |
| `src/Enum` | Listes de valeurs, sans comportement. |
| `src/DataFixtures` | Données de test, pas du code de l'application. |
| Autres `src/Repository/*` | Générés par le maker : un constructeur et des exemples commentés. |
| `src/Kernel.php` | Fichier fourni par Symfony. |

Le but est de mesurer ce qui peut contenir une erreur de raisonnement : du code généré à 100 % ne prouverait rien.

## Résultat

| Suite | Tests | Assertions |
|---|---|---|
| Unit | 137 | 373 |
| Integration | 105 | 547 |
| Functional | 267 | 1359 |
| **PHPUnit, total** | **509** | **2279** |
| Cypress e2e | 61 (12 specs) | — |

```
Code Coverage Report:
 Summary:
  Classes: 100.00% (42/42)
  Methods: 100.00% (162/162)
  Lines:   100.00% (917/917)
```

Les 42 classes du périmètre sont à 100 % en lignes et en méthodes. Le rapport HTML est généré dans `backend/var/coverage/` par `make coverage`. Les 61 tests Cypress passent (`make e2e`). Chiffres de la branche `feature/back-office` (i18n côté API, back office et « Mon espace »).

| Étape | PHPUnit | Cypress | Classes du périmètre |
|---|---|---|---|
| Première campagne | 155 | 13 | 11 |
| Volet social, lot 1 (relations et blocage) | +98 → 253 | +9 → 22 (24 avec la refonte) | +8 → 19 |
| Volet social, lot 2 (looks du jour) | +120 → 373 | +34 → 58 | +11 → 30 |
| i18n côté API (traduction, langue du compte et de l'en-tête) | +51 → 424 | — | +1 → 31 |
| Back office (EasyAdmin, KPI) et « Mon espace » | +85 → 509 | +3 → 61 | +11 → 42 |

Règles du lot 2 : [docs/decisions.md](docs/decisions.md), sections « Volet social » et « Lot 2 ».

## Ce que vérifie chaque fichier de test

### Unit (`backend/tests/Unit/`)

**`Service/ClothingScannerTest.php`** (28 tests). Le scanner est testé sans réseau (`MockHttpClient`) et sans attente (`MockClock`).
- Configuration : clé API absente, liste de modèles vide ou faite d'espaces.
- Succès avec le premier modèle : résultat normalisé et requête envoyée à OpenRouter (en-tête `Authorization`, modèle, consigne, image, délai de 30 s).
- Modes : `label` (consigne « étiquette », marque, taille, composition), `photo`, et mode inconnu qui revient à `photo`.
- Réponse du modèle : JSON entouré de texte ou d'un bloc Markdown, contenu découpé en blocs (dont un bloc sans texte).
- Normalisation :
  - catégorie connue, en majuscules, inconnue (`other`) ou absente ;
  - texte `"null"`, espaces seuls ;
  - texte de 300 caractères tronqué à 255 ;
  - tableau à la place d'un texte, nombre converti en texte.
- Réponses illisibles : texte sans JSON, accolades inversées (`} rien {`), JSON invalide, contenu vide. Dans chaque cas, on passe au modèle suivant sans nouvelle tentative.
- Erreurs :
  - **429 puis succès** : seconde tentative sur le même modèle après exactement 1,5 s ;
  - **5xx deux fois** : passage au modèle suivant, une seule pause ;
  - réponse HTML non JSON en 502 : nouvelle tentative ;
  - **4xx** : modèle suivant, sans pause ;
  - `error` dans un corps en 200 : traité comme un échec ;
  - **erreur réseau** : modèle suivant, sans pause.
- Échec final :
  - message « modèles saturés » si tous les échecs viennent d'une saturation ;
  - message générique sinon (réponse illisible, erreur réseau) ;
  - un seul avertissement dans le journal, avec le détail de chaque modèle.
- Budget de temps : le délai de la seconde tentative est réduit au temps restant (23,5 s après un premier essai de 25 s et une pause de 1,5 s). L'analyse s'arrête avant les modèles suivants quand il reste moins de 5 s.

**`Service/ItemPhotoStorageTest.php`** (14 tests). Les fichiers sont écrits dans un dossier temporaire.
- JPEG valide : nom aléatoire `{id}-{16 hex}.jpg` et contenu identique à l'envoi.
- Limite de taille : image de **2048 px** acceptée, de **2049 px** refusée, en largeur comme en hauteur.
- Contenus refusés :
  - PNG annoncé comme JPEG (le contenu réel est vérifié) ;
  - type annoncé autre que JPEG ;
  - base64 invalide ;
  - base64 valide mais pas une image ;
  - chaîne sans préfixe `data:`.

  Dans chaque cas, aucun fichier n'est écrit et la photo de l'objet n'est pas modifiée.
- Remplacement : l'ancienne photo est supprimée.
- Photo externe (`https://…`) : jamais supprimée, même si un fichier du même nom existe.
- Chemin forgé avec `../` : la suppression ne sort pas du dossier des photos.

**`Security/UserCheckerTest.php`** (3 tests). Compte suspendu refusé avec le message « Ce compte est suspendu. », compte actif accepté, autre type d'utilisateur ignoré.

**`State/OwnerProcessorTest.php`** (6 tests).
- Pour un logement comme pour un objet : l'utilisateur connecté devient propriétaire, et un propriétaire déjà renseigné n'est pas remplacé.
- Requête anonyme ou utilisateur d'une autre classe : pas de propriétaire.
- L'enregistrement est toujours délégué au processor Doctrine, avec les mêmes arguments.

**`Service/ScanFailedExceptionTest.php`** (2 tests). Message et détail des erreurs conservés, détail facultatif.

**`EventListener/ItemPhotoCleanerTest.php`** (1 test). Le fichier de la photo est supprimé quand l'objet l'est.

**`Entity/UserTest.php`** (7 tests). `isMinor()` :
- 12 ans ;
- 18 ans demain, 18 ans aujourd'hui ;
- adulte ;
- né un 29 février ;
- date du jour par défaut ;
- date de naissance absente.

**`Entity/ShareLinkTest.php`** (7 tests). `isActive()` :
- sans date limite, expiration future, expiré hier ;
- **expiration à l'instant même** (inactif) ;
- révoqué, révoqué avant sa date limite ;
- date du jour par défaut.

**`Service/ItemExposureTest.php`** (9 tests, lot 2). Objet montrable dans un look (règle portée par `Item::isShareable`) :
- montrable : tout ouvert aux abonnés, pièce et rangement réservés aux amis proches ;
- jamais : objet perso, rangement perso ou privé, pièce perso ou privée, logement privé ;
- objet sans emplacement : jamais.

**`Service/OotdPhotoStorageTest.php`** (8 tests, lot 2).
- Photo d'un look dans `uploads/ootds/`, sous un nom aléatoire ; contenu autre que JPEG refusé.
- Remplacement : l'ancien fichier est supprimé ; suppression du fichier envoyé.
- Jamais supprimés : la photo de démo des fixtures (`uploads/demo/`), une photo externe, un chemin forgé avec `../`.
- `OotdPhotoCleaner` supprime la photo avec le look.

**`Validator/ShareableItemsValidatorTest.php`** (6 tests, lot 2). Mes objets partageables sont acceptés ; l'objet d'une autre personne est refusé ; un nouveau look appartient à l'utilisateur connecté ; sans propriétaire ni utilisateur connecté, tout objet est refusé ; les valeurs qui ne sont pas des objets sont ignorées ; une autre contrainte est rejetée.

### Integration (`backend/tests/Integration/`)

**`Repository/RelationshipRepositoryTest.php`** (9 tests). `countReceived()` sur la vraie base de test, par statut et par type.
- Camille : 4 relations acceptées, dont 3 abonnés (Sophie, Théo, Marc) et 1 amie proche ; 1 demande en attente.
- Relation réciproque : Sophie a reçu une relation « amie proche » de Camille.
- Les demandes envoyées ne comptent pas : Théo a envoyé, il n'a rien reçu. Zoé n'a aucune relation.

**`Doctrine/CurrentOwnerExtensionTest.php`** (23 tests). L'extension est appliquée à de vraies requêtes, avec le vrai service `Security`.
- Pour chaque niveau (logement, pièce, rangement, catégorie, objet), Camille obtient la **liste exacte** de son contenu, perso et privé compris, sans le studio de son compte enfant.
- Sacha n'obtient que son studio. Théo, abonné sans contenu, et un anonyme n'obtiennent rien.
- En lecture d'un élément : Théo ne trouve pas la Maison, Sophie ne trouve pas une catégorie du garage, en passant par les jointures pièce > logement.
- Les ressources hors hiérarchie (les looks) ne sont pas filtrées.

**`Service/RelationshipCheckerTest.php`** (15 tests). Matrice des droits sur le contenu de Camille, d'après les fixtures et [decisions.md](docs/decisions.md) :

| Qui | Situation | abonné | ami proche | enfant | voit | commente |
|---|---|---|---|---|---|---|
| Sacha | enfant +18 | ❌ | ❌ | ✅ | ✅ | ✅ |
| Lou | enfant -18 | ❌ | ❌ | ✅ | ✅ | ❌ |
| Sophie | amie proche et abonnée | ✅ | ✅ | ❌ | ✅ | ✅ |
| Théo | abonné | ✅ | ❌ | ❌ | ✅ | ❌ |
| Nina | demande en attente | ❌ | ❌ | ❌ | ❌ | ❌ |
| Nina | **amie proche acceptée, sans abonnement** | ✅ | ✅ | ❌ | ✅ | ✅ |
| Marc | bloqué par Camille, ou qui a bloqué Camille | – | – | – | ❌ | ❌ |
| Sacha | **enfant bloqué** | ❌ | ❌ | ✅ | ❌ | ❌ |
| Zoé | inconnue | ❌ | ❌ | ❌ | ❌ | ❌ |

Plus : le propriétaire a tous les droits sur son contenu, le blocage agit dans les deux sens, une relation a un sens (Camille ne suit pas Théo), et un petit-enfant n'a aucun droit chez sa grand-mère.

**`Entity/BlockValidationTest.php`** (3 tests). Blocage valide, se bloquer soi-même refusé, doublon refusé (« Cette personne est déjà bloquée. »).

**`Doctrine/PublicUserExtensionTest.php`** (1 test). Sans utilisateur connecté, l'extension ne renvoie personne, ni en recherche ni en lecture d'un profil (cas que l'API ne peut pas atteindre, puisqu'elle exige un jeton).

**`Entity/OotdTest.php`** (11 tests, lot 2). Validation d'un `Ootd` sur la vraie base :
- un nouveau look est un brouillon porté aujourd'hui ; un brouillon sans photo est valide ;
- **publier sans photo est refusé**, avec photo accepté ;
- date passée acceptée, **date future refusée** ; légende limitée à 500 caractères ;
- un objet n'est ajouté qu'une fois ; supprimer un objet le retire du look ; supprimer le propriétaire supprime ses looks ;
- les fixtures correspondent à la matrice des droits.

**`Security/OotdRightsTest.php`** (30 tests, lot 2). Matrice des droits sur les looks de Camille, appliquée à la fois par `OotdVisibilityExtension` (requêtes) et par `OotdVoter` :
- le fil de chaque compte de la matrice (Camille, Sacha, Lou, Sophie, Théo, Nina, Marc abonné puis bloqué, Zoé) contient exactement les looks attendus ;
- **l'extension et `RelationshipChecker::canSeeOotdOf` sont comparés pour chaque look publié** : la règle est écrite deux fois (DQL et PHP), le test garantit qu'elles disent la même chose ;
- seule la propriétaire voit et modifie son brouillon ; « mes looks » contient les brouillons et se filtre par `published` ;
- un anonyme ne voit rien ; les autres ressources ne sont pas filtrées.

### Functional (`backend/tests/Functional/`)

**`AuthenticationTest.php`** (12 tests).
- Connexion JWT réussie : le jeton obtenu ouvre `/api/me`.
- Mauvais mot de passe et compte inconnu : **401 avec le même message**, ce qui ne révèle pas si le compte existe.
- Compte suspendu : 401 avec « Ce compte est suspendu. ».
- Six routes protégées répondent 401 sans jeton, et un jeton invalide est refusé.
- La racine de l'API reste publique.

**`MeTest.php`** (5 tests). `/api/me` de Camille :
- 14 objets ;
- **3 abonnés** (Sophie, Théo, Marc) ;
- **1 amie proche** (Sophie ; la demande de Nina est en attente) ;
- **1 demande en attente** (Nina) ;
- comptes enfants Sacha (majeur) et Lou (mineur).

Les compteurs sont vides pour Théo. La relation réciproque est comptée pour Sophie, et l'administrateur a bien le rôle `ROLE_ADMIN`.

**`IsolationTest.php`** (10 tests).
- Théo ne voit pas la Maison de Camille : **404**.
- Sophie, amie proche, ne voit ni la pièce, ni le rangement, ni la catégorie du garage par l'API : 404. Les Voters ne sont pas encore écrits.
- Collections : 2 logements pour Camille, 1 pour Sacha, 0 pour Théo.
- Un logement créé par Théo lui appartient et reste invisible pour Camille.

**`ScanTest.php`** (13 tests). `/api/scan` :
- non configuré : 503 ;
- succès ;
- choix de la consigne selon le mode (`label`, `photo`, absent, inconnu) ;
- tous les modèles saturés : 502 ;
- image absente, vide, d'un autre type, PNG ou corps non JSON : 400 ;
- plus de 3 Mo : 413.

Dans tous les cas d'erreur, aucun appel n'est envoyé au modèle. Le scanner simulé est un vrai `ClothingScanner` branché sur `MockHttpClient` et `MockClock`.

**`UserSearchTest.php`** (13 tests). `GET /api/users?username=…` :
- recherche partielle, insensible à la casse et aux espaces ; 400 sous 2 caractères ;
- jamais trouvés : moi-même, le compte suspendu, Lou (-18, même par sa mère), ni Camille et Marc l'un pour l'autre après un blocage ; Sacha (enfant majeur) est trouvé ;
- `__` et `%%` ne trouvent personne (caractères génériques échappés) ; 20 résultats par page ;
- seules les clés `id`, `username`, `avatar`, `bio` sont renvoyées : ni email, ni mot de passe, ni rôles, ni date de naissance, ni parent, ni logements.

**`PublicProfileTest.php`** (18 tests). `GET /api/users/{id}` :
- mêmes vérifications des données sensibles, y compris dans le texte brut de la réponse (email, date de naissance, adresse de la Maison) ;
- mon lien avec le compte (`viewerRelationship`) vu par Théo, Sophie, Nina, Zoé et Camille elle-même ;
- 404 : compte suspendu, Lou vue par un autre que sa mère, identifiant inconnu, blocage dans les deux sens ; 401 sans jeton ;
- **lecture seule** : POST, PUT, PATCH et DELETE répondent 405, sans rien modifier.

**`RelationshipApiTest.php`** (32 tests). `/api/relationships` :
- envoi : demandeur et statut imposés par le serveur ; à soi-même, doublon, type manquant → 422 ; destinataire manquant → 400 ; compte inexistant, suspendu, -18 (même pour son parent) ou bloqué dans un sens ou dans l'autre → **404** ;
- acceptation : par la destinataire seulement (demandeuse → 403, tiers → 404), seule transition `pending` → `accepted` (422 sinon, 400 pour un statut inconnu), type et destinataire non modifiables ;
- suppression : refuser, annuler, se désabonner, retirer un abonné ; un tiers → 404 ;
- liste : seulement mes relations, filtres `direction`, `status`, `type` (400 sur une valeur inconnue).

**`BlockApiTest.php`** (17 tests). `/api/blocks` :
- bloquer Marc supprime son abonnement ; bloquer Sophie supprime ses 3 relations avec Camille, sans toucher aux autres ;
- bloqueur imposé par le serveur ; se bloquer soi-même → 422 ; parent ↔ enfant (Sacha, Lou, dans les deux sens) → 422 ;
- compte introuvable, suspendu, -18 d'une autre famille, déjà bloqué ou qui m'a bloqué → 404 ;
- effets dans les deux sens : recherche vide, profil 404, demande 404 ;
- liste limitée à mes blocages ; la personne bloquée ne voit ni ne supprime le blocage ; débloquer ne rétablit pas les relations.

**`ItemPhotoTest.php`** (14 tests). `/api/items/{id}/photo` :
- envoi : fichier écrit et URL enregistrée en base ;
- suppression : fichier et URL retirés ; une suppression sans photo répond aussi 204 ;
- objet d'un autre utilisateur ou objet inconnu : 404, en envoi comme en suppression, et la photo n'est pas modifiée ;
- six cas de 400 (refus par le contrôleur ou par `ItemPhotoStorage`) ;
- 413 au-delà de 3 Mo.

**`OotdApiTest.php`** (40 tests, lot 2). `/api/ootds`, `/api/me/ootds`, `/api/users/{id}/ootds` :
- **matrice des droits** en lecture (8 comptes) et looks d'un profil (Théo et Sacha oui ; Nina, Zoé, Marc bloqué non ; Camille sans son brouillon) ;
- mes looks : brouillons et publiés séparés par `?published=` ; 401 sans jeton ;
- objets d'un look exposés **sans leur emplacement** ; un objet devenu perso après la publication est masqué aux autres ;
- création : brouillon avec des objets partageables, date du jour par défaut, propriétaire imposé par le serveur ;
- **objets refusés (422)** : objet perso, pièce privée, rangement privé ou perso, pièce perso, logement privé ; l'objet d'un autre et un objet inconnu donnent la même réponse (400 « introuvable ») ;
- champ `shareable` de `GET /api/items` (lot 2, `Item::isShareable`) ;
- date future refusée ; **publier exige une photo** ; publier un brouillon avec photo ; publier deux fois garde la première date ; **pas de retour en brouillon** ; un PATCH revalide les objets ;
- suppression par la propriétaire ; Sophie peut voir mais pas supprimer (403), Zoé ne voit même pas le look (404) ;
- fil : 10 looks par page.

**`OotdPhotoTest.php`** (16 tests, lot 2). `/api/ootds/{id}/photo` :
- la propriétaire ajoute une photo puis publie ; remplacer la photo d'un look de démo ne supprime pas le fichier de démo ;
- retirer la photo d'un brouillon ; **un look publié garde sa photo** (422 si on la retire) ; supprimer le look supprime sa photo ;
- Théo voit le look publié mais ne change pas sa photo (403) ; brouillon ou look invisible : 404 ; look inconnu : 404 ;
- image absente, corps non JSON, PNG annoncé comme JPEG, image trop grande ou trop lourde : refusées.

### E2E (`frontend/cypress/e2e/`)

| Spec | Tests | Parcours |
|---|---|---|
| `login.cy.js` | 5 | Nom et logo Lendly ; connexion de Camille, arrivée sur le fil puis son dressing ; mauvais mot de passe (« Email ou mot de passe incorrect. ») ; **compte suspendu** (« Ce compte est suspendu. ») ; page protégée → connexion → retour à la page demandée |
| `navigation.cy.js` | 8 | Barre : fil, Mon dressing, Tous mes objets ; recherche depuis Mon dressing ; ouvrir et fermer « Poster un look » ; « Trouver des amis ». Hiérarchie : pièces de la Maison ; Dressing > Penderie (catégories et objets exacts) ; dépliage du Coffre à bijoux ; retour à Mon dressing puis Grenier |
| `add-item.cy.js` | 3 | Ajout d'un « Pull rayé » « Au sale » dans Penderie › Hauts, toujours présent après rechargement ; liste des catégories proposées ; fermeture du formulaire sans ajout |
| `item.cy.js` | 1 | Statut changé depuis les pilules d'état de la fiche, gardé après rechargement |
| `logout.cy.js` | 2 | Déconnexion depuis les paramètres du profil ; pages protégées inaccessibles ensuite |
| `friends.cy.js` | 9 | Accepter la demande de Nina depuis la cloche (le badge disparaît) ; la refuser ; bandeau du profil → page Amis ; Zoé cherche et suit Camille puis annule ; Lou absente de la recherche ; profil introuvable ; Camille bloque Marc puis le débloque (abonnement non rétabli) ; Marc ne trouve plus Camille ; blocage de Sacha refusé avec un message |
| `feed.cy.js` | 7 | Lot 2. Fil de Camille : le look de Sophie et les siens, du plus récent au plus ancien, sans son brouillon ; mes objets ouvrent leur fiche ; Théo voit les objets de Camille en lecture seule, sans emplacement ; « Voir plus » ; fil vide de Zoé avec des pistes ; looks d'un profil public vus par Théo, pas par Zoé |
| `post-look.cy.js` | 9 | Lot 2. Seuls les objets partageables sont proposés ; publier avec une photo de la galerie et deux objets ; date du jour, pas de date future ; **photo demandée avant de publier, sans rien envoyer** ; **un seul look après un échec puis un nouvel essai** ; photo par la caméra ; brouillon sans photo ; reprendre un brouillon et le publier ; supprimer un brouillon |
| `profile.cy.js` | 6 | Lot 2. Mes looks publiés, du plus récent au plus ancien ; un objet porté ouvre sa fiche ; « Brouillons · 1 » ouvre la reprise ; le compteur suit la suppression ; profil sans look → « Poster mon look » ; la carte de Mon dressing ouvre « Poster un look » |
| `delete-look.cy.js` | 5 | Lot 2. Supprimer mon look depuis le fil (sans recharger, il disparaît aussi du profil) ou depuis le profil ; « Garder » le conserve ; **aucun bouton sur le look de Sophie**, ni sur son profil public |
| `scan.cy.js` | 3 | Lot 2 (caméra partagée avec « Poster un look »). Photo par la caméra et fiche pré-remplie ; photo importée ; caméra coupée en quittant l'écran. La réponse de l'IA est simulée (`cy.intercept`) |

Les éléments utilisés portent des attributs `data-cy`. Chaque test recharge les fixtures `test` avant de commencer : l'ajout d'objet, joué en premier, ne fausse pas la navigation, qui vérifie la liste exacte des objets. Les looks envoyés avec une photo pendant `post-look.cy.js` sont supprimés par l'API après chaque test, et leur photo avec eux : rien ne reste dans `uploads/ootds/`. Dans Cypress, la caméra est la caméra simulée d'Electron.

## Anomalies trouvées pendant la campagne de test

Les deux anomalies ont été repérées **en lisant le code pendant l'écriture des tests**, pas par un outil automatique : PHPStan était à 0 erreur sur le code d'origine, et la couverture n'a pas été mesurée avant les corrections.

1. **Bloc « erreur réseau » jamais exécuté dans `ClothingScanner`.** Le `catch (ExceptionInterface)` prévu pour les délais dépassés et les erreurs réseau était placé après `catch (\RuntimeException)`. Or `TransportException` et `TimeoutException` de Symfony HttpClient héritent de `\RuntimeException` : ces erreurs tombaient dans le premier bloc, et le second était du code mort.
   - **Repéré** en préparant le cas « erreur réseau » de `ClothingScannerTest`.
   - **Confirmé** par la déclaration de la classe (`class TransportException extends \RuntimeException`), puis par un script exécuté dans le conteneur : une erreur réseau simulée avec `MockResponse` donnait une `TransportException`, de type `\RuntimeException`, avec le code 0.
   - **Corrigé** en inversant les deux blocs. Le comportement visible est resté le même (pas de nouvelle tentative, modèle suivant, message générique). Seule la ligne de journal de ce cas change.
   - **Vérifié ensuite** par les tests `testNetworkErrorMovesToNextModelWithoutRetry` et `testNetworkErrorOnEveryModelGivesGenericMessage`, qui passent dans le bloc réseau.
2. **Message « compte suspendu » masqué dans `LoginView`.** Le front remplaçait toute réponse 401 par « Email ou mot de passe incorrect. ». Le message « Ce compte est suspendu. » renvoyé par l'API n'était donc jamais affiché.
   - **Repéré** en lisant `LoginView.vue` pendant l'écriture de la spec `login.cy.js`.
   - **Corrigé** avant le premier lancement de Cypress : le message générique est désormais réservé à `Invalid credentials.`.
   - **Confirmé** par une mutation : avec l'ancien code remis temporairement, le test « refuse un compte suspendu avec un message clair » échoue (voir le tableau ci-dessous).

3. **Brouillons en double dans « Poster un look » (lot 2).** Quand une publication échouait après la création du look (envoi de la photo raté, par exemple), chaque nouvel essai recréait un brouillon, car l'écran ne retenait pas l'identifiant du look créé.
   - **Repéré** par une mutation : en neutralisant la vérification « photo obligatoire » du front, les tests passaient encore, car le back renvoie le même message. En regardant pourquoi, un brouillon était créé en douce.
   - **Corrigé** : `PostLookView` retient l'identifiant du look créé et le met à jour aux essais suivants.
   - **Vérifié** par deux tests de `post-look.cy.js` : aucune requête envoyée sans photo, et un seul look créé après deux essais. La mutation qui retire la correction est détectée.

## Contrôle par mutation

Une couverture de 100 % prouve que chaque ligne s'exécute, pas que les tests détectent une erreur. Des bugs plausibles ont donc été introduits à la main, un par un, puis le code a été restauré.

| Bug introduit | Résultat |
|---|---|
| `ItemPhotoStorage` : `> MAX_DIMENSION` devient `>=` (une image de 2048 px serait refusée) | Détecté (suite unit en échec) |
| `ShareLink::isActive` : `expiresAt > $at` devient `>=` (un lien resterait actif à l'instant de son expiration) | Détecté (suite unit en échec) |
| `ClothingScanner` : plus de nouvelle tentative sur les erreurs 5xx | Détecté (suite unit en échec) |
| `LoginView` (e2e) : retour à l'ancien message unique pour toute réponse 401 | Détecté (« refuse un compte suspendu » en échec) |

**Volet social, lot 1** : chaque bug a été introduit seul, puis le fichier restauré à l'identique.

| Classe | Bug introduit | Tests en échec |
|---|---|---|
| `RelationshipChecker` | un ami proche n'a plus les droits d'un abonné | 1 |
| `RelationshipChecker` | le blocage est ignoré pour voir | 3 |
| `RelationshipChecker` | un enfant -18 peut commenter | 1 |
| `BlockRepository` | le blocage ne compte que dans un sens | 3 |
| `PublicUserExtension` | comptes suspendus visibles | 2 |
| `PublicUserExtension` | `%` et `_` non échappés dans la recherche | 1 |
| `PublicUserExtension` | comptes -18 visibles par tout le monde | 2 |
| `PublicUserExtension` | blocage dans un seul sens | 2 |
| `RelationshipProcessor` | demande à un compte -18 autorisée | 1 |
| `RelationshipProcessor` | transition `pending` → `accepted` non vérifiée | 1 |
| `RelationshipExtension` | les relations des autres deviennent visibles | 16 |
| `Relationship` (sécurité) | la demandeuse peut accepter sa propre demande | 1 |
| `RelationshipRepository` | relations supprimées dans un seul sens au blocage | 3 |
| `BlockProcessor` | relations non supprimées au blocage | 3 |
| `BlockProcessor` | un enfant peut bloquer son parent | 1 |
| `BlockExtension` | les blocages des autres deviennent visibles | 2 |
| `Block` (groupes) | le client choisit le bloqueur | 1 |
| `FriendsView` (e2e) | le badge de la cloche n'est plus rechargé après une action | 1 |

**Volet social, lot 2 (looks du jour)** : même méthode, un bug à la fois, fichier restauré ensuite. Pour le back, la suite PHPUnit complète est relancée (sauf les deux premières lignes, lancées sur les tests des looks et des objets partageables). Pour le front, la spec Cypress concernée. Les mutations des classes back ont été rejouées pendant la mise à jour de cette documentation (commit 9) : celles faites en écrivant ces classes n'avaient pas été notées.

| Classe ou écran | Bug introduit | Tests en échec |
|---|---|---|
| `Item::isShareable` | un objet perso devient partageable | 5 |
| `Item::isShareable` | un objet d'un logement privé devient partageable | 1 |
| `OotdVoter` | un ami voit un brouillon | 6 |
| `OotdVoter` | un ami peut modifier ou supprimer un look | 12 |
| `OotdVisibilityExtension` | les brouillons apparaissent dans le fil | 15 |
| `OotdVisibilityExtension` | le brouillon d'une autre personne est lisible | 9 |
| `OotdVisibilityExtension` | une demande en attente suffit pour voir les looks | 4 |
| `OotdNormalizer` | un objet devenu privé reste montré aux autres | 1 |
| `PhotoStorage` | suppression d'un fichier hors du dossier des photos | 1 |
| `PostLookView` (e2e) | publier sans photo passe côté front | 1 (après renforcement du test, voir ci-dessous) |
| `PostLookView` (e2e) | un nouvel essai recrée un brouillon | 1 |
| `PostLookView` (e2e) | une date future est proposée | 1 |
| `PostLookView` (e2e) | les objets non partageables sont proposés | 1 |
| `ScanView` (e2e) | la caméra n'est plus coupée en quittant le scanner | 1 |
| `ProfileView` (e2e) | tri des looks retiré | 2 |
| `ProfileView` (e2e) | brouillons et publiés inversés | 3 |
| `ProfileView` (e2e) | un brouillon ouvre un nouveau look | 2 |
| `MyDressingView` (e2e) | « Poster mon look » de nouveau désactivé | 1 |
| `OotdCard` (e2e) | bouton de suppression sur le look d'une autre personne (fil) | 1 |
| `OotdCard` (e2e) | bouton de suppression sur un profil public | 1 |
| `OotdCard` (e2e) | la carte reste affichée après la suppression | 2 |

La mutation « publier sans photo passe côté front » n'a **pas été détectée au premier essai** : le back renvoie exactement le même message que le front, donc le test qui ne vérifiait que le message passait. Le test vérifie maintenant qu'aucune requête n'est envoyée. C'est en cherchant pourquoi que l'anomalie n° 3 a été trouvée.

**Internationalisation, côté API** (9 mutations, toutes détectées) :

| Bug introduit | Tests en échec |
|---|---|
| Langue du compte ignorée | 10 |
| `UserLocaleSubscriber` placé avant le firewall (utilisateur pas encore connu) | 7 |
| Langue enregistrée en base non vérifiée | 1 |
| `PATCH /api/me` accepte d'autres champs que la langue | 1 |
| Scanner sans la langue de la requête | 1 |
| Clé allemande supprimée | 3 |
| Clé utilisée dans le code mais absente des traductions | 4 |
| Libellé d'enum manquant en anglais | 2 |
| `Accept-Language` ignoré (configuration) | 5 |

**Back office et « Mon espace »** (39 mutations, toutes détectées ; trois l'ont été après un renforcement des données de test, voir ci-dessous) :

| Classe ou écran | Bug introduit | Tests en échec |
|---|---|---|
| `LastLoginSubscriber` | dernière connexion mise à jour à chaque requête JWT | 2 |
| `LastLoginSubscriber` | jamais enregistrée / enregistrée sans flush / back office oublié | 3 / 3 / 1 |
| `security.yaml` | back office ouvert à `ROLE_USER` | 1 |
| `security.yaml` | comptes suspendus acceptés au back office | 1 |
| `security.yaml` | formulaire de connexion sans CSRF | 1 |
| `UserCrudController` | un admin peut se suspendre ou se retirer le rôle | 2 |
| `UserCrudController` | suppression de compte possible / email modifiable / date de naissance affichée | 1 / 1 / 2 |
| `SecurityController` | retour vers « / » après la connexion | 1 |
| `OotdCrudController` | brouillons listés / brouillon ouvert par son adresse / looks modifiables | 1 / 1 / 1 |
| `ItemCrudController` | tous les objets listés / objet privé ouvert par son adresse / emplacement affiché | 1 / 3 / 2 |
| `ItemRepository` | règle en requête sans la condition « logement privé » | 1 (après ajout d'un test) |
| `worn_items.html.twig` | objet devenu perso montré dans le look | 1 |
| `CategoryCrudController` | catégorie pleine supprimée / création possible | 1 / 1 |
| `AdminStats` | brouillons comptés dans les looks / médiane remplacée par la moyenne | 1 / 1 |
| `AdminStats` | personnes venues = inscrits / semaine qui déborde sur la suivante | 1 / 1 (après renforcement des dates de test) |
| `AdminStats` | tous les objets montrables / taux sans les demandes en attente / types vides oubliés | 1 / 2 / 1 |
| `AdminStats` | tous les comptes suspendus / période de 7 jours remplacée par 30 | 1 / 1 |
| `MySpace` | brouillons comptés comme portés / blocage subi révélé dans l'export | 2 / 1 |
| `MySpace` | abonnés comptés deux fois / email des autres dans l'export / « plus portés » non triés | 1 / 2 / 1 |
| `MySpaceController` | export affiché au lieu d'être téléchargé | 1 |
| `ProfileView`, `MySpaceView` (e2e) | lien vers « Mon espace » cassé / langue non envoyée | 3 / 1 |

Trois mutations n'ont d'abord pas été détectées, faute de données qui fassent la différence : aucun logement privé avec des pièces ouvertes (règle en requête), aucune connexion d'une personne inscrite dans une autre période (personnes venues), aucune date tombant pile un lundi à minuit (semaines). Les tests ont été complétés, puis les mutations rejouées et détectées.

Une mutation du lot 1 n'a pas été détectée : ne plus forcer le statut `pending` à la création d'une demande. Ce n'était pas un trou dans les tests mais une ligne sans effet : le statut ne fait pas partie des champs modifiables à la création, et une relation est `pending` par défaut. La ligne a été retirée ; le test `testRequesterAndStatusAreAlwaysSetByTheServer` vérifie toujours qu'un statut envoyé par le client est ignoré.

## Choix techniques

- **Horloge injectée.** `ClothingScanner` reçoit `Symfony\Component\Clock\ClockInterface` au lieu d'appeler `microtime()` et `usleep()`. En production, `NativeClock` attend réellement 1,5 s (mesuré : 1,507 s). En test, `MockClock` avance instantanément, ce qui permet de vérifier le budget de 50 s et les pauses sans ralentir la suite.
- **MockHttpClient.** Aucun test n'appelle OpenRouter. Les réponses (succès, 429, 5xx, HTML, erreur réseau) sont simulées, et les requêtes envoyées sont inspectées. Les tests fonctionnels remplacent le service par un vrai `ClothingScanner` branché sur ce client : le code testé est celui de la production.
- **`dama/doctrine-test-bundle`.** Chaque test PHPUnit s'exécute dans une transaction annulée à la fin : la base reste dans l'état des fixtures, et la suite peut être relancée indéfiniment.
- **Base de test séparée.** `.env.test` pointe vers le conteneur PostgreSQL, et Doctrine ajoute le suffixe `_test` : la base `penderie_db_test` est distincte de `penderie_db`. `.env.local` n'est pas lu en environnement de test.
- **En-tête `X-Penderie-Env: test`, réservé au Docker local.** Les tests Cypress utilisent l'application lancée sur `http://localhost:5173`. Un `cy.intercept()` ajoute cet en-tête à toutes les requêtes `/api` et `/uploads`. La configuration nginx ([docker/nginx/default.conf](docker/nginx/default.conf)) transmet alors `APP_ENV=test` à Symfony, qui utilise `penderie_db_test`. Sans l'en-tête, rien ne change.

  ⚠️ **Cette configuration ne doit jamais être utilisée en production** : n'importe qui pourrait envoyer l'en-tête et passer l'application en environnement de test, en mode debug et sur une autre base. Après les tests Cypress, la base de dev a été comparée à un instantané pris avant : elle est identique.
- **404 plutôt que 400 pour un compte introuvable (volet social).** Une demande ou un blocage vise un compte par son IRI (`/api/users/{id}`), résolue à travers `PublicUserExtension`. Avec l'option `denormalize_throw_on_relation_not_found: false`, un compte invisible (bloqué, suspendu, -18, inexistant) laisse le champ vide au lieu de déclencher une erreur 400, et le processor répond 404 : rien ne révèle un blocage.
- **Validation dans le processor.** Le doublon et la demande à soi-même dépendent du demandeur, qui n'est connu qu'une fois l'utilisateur connecté renseigné par le processor : celui-ci lance donc lui-même la validation, après coup.
- **Journal du kernel en fichier en test.** Sans Monolog, Symfony écrit son journal sur la sortie d'erreur. En test, il est redirigé vers `backend/var/cache/test/test.log`, pour que les 404 attendues n'apparaissent pas comme des erreurs dans le terminal. Les échecs de test restent affichés par PHPUnit.

## Limites

- **Couverture des lignes seulement.** PCOV mesure les lignes exécutées, pas chaque branche d'une condition. Les cas limites listés plus haut compensent en partie, mais ne garantissent pas que toutes les combinaisons sont testées.
- **Contrôle par mutation manuel.** 92 mutations ont été essayées à la main : 4 pour la première campagne, 19 pour le lot 1 du volet social (dont une non détectée, expliquée plus haut), 21 pour le lot 2, 9 pour l'i18n côté API et 39 pour le back office et « Mon espace » (trois détectées seulement après renforcement des tests). Un outil comme Infection en essaierait des centaines.
- **Back office testé côté serveur.** Les pages EasyAdmin sont testées par des requêtes PHPUnit (`WebTestCase`), pas dans un navigateur : le JavaScript d'EasyAdmin (fenêtres de confirmation, recherche) n'est vérifié qu'à la main.
- **Règle « partageable » hors du périmètre mesuré.** Elle vit dans `Item::isShareable()` (`src/Entity`, exclu). Le 100 % ne la mesure pas : elle est couverte par `ItemExposureTest`, `OotdApiTest` et deux mutations.
- **Règles de visibilité de la hiérarchie pas encore testables.** Les scénarios de [docs/campagne-test.md](docs/campagne-test.md) sur le contenu des logements (Théo voit la Penderie mais pas le carnet perso, Sophie voit le garage, liens de partage…) dépendent de Voters qui ne sont pas écrits. Pour l'instant, `CurrentOwnerExtension` limite chacun à son propre contenu, et c'est ce comportement provisoire qui est testé. Les **looks du jour**, eux, ont leur règle complète depuis le lot 2 (`OotdVoter`, `OotdVisibilityExtension`), testée sur toute la matrice.
- **Front du volet social.** Les écrans Amis, profil public, comptes bloqués, fil, « Poster un look » et profil sont couverts par les specs Cypress uniquement, sans tests unitaires des composants.
- **Fil : pagination décalée après une suppression.** Supprimer un look déjà affiché dans le fil décale la page suivante : « Voir plus » peut sauter un look, qui réapparaît au rechargement. Non testé, voir [audit.md](audit.md).
- **Configuration API Platform non couverte.** Les expressions `securityPostDenormalize` (créer une pièce dans le logement d'un autre), les validations des entités et les opérations PATCH/DELETE générées ne sont pas incluses dans la mesure, et ne sont testées qu'indirectement.
- **OpenRouter jamais appelé.** Si le format de réponse réel change, les tests ne le verront pas.
- **Front partiellement couvert.** Il n'y a pas de tests unitaires des composants Vue. Les parcours e2e passent maintenant par Mon dressing, la fiche objet et, depuis le lot 2, par le scanner (réponse de l'IA simulée), la caméra (simulée par Electron) et l'envoi de la photo d'un look ; ils ne passent pas par l'envoi de la photo d'un objet ni par l'enregistrement d'un objet scanné.
- **E2E en local uniquement.** Les tests Cypress tournent sur la machine hôte contre le serveur de développement Vite. Aucun pipeline ne les lance automatiquement. Le rechargement des fixtures avant chaque test ajoute environ une seconde par test.
- **Photos des tests fonctionnels.** Elles sont écrites dans les vrais dossiers `backend/public/uploads/items/` et `uploads/ootds/`, puis supprimées par chaque test. Les photos de démo (`uploads/demo/`) ne sont jamais supprimées.
