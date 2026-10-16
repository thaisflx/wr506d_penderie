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

Tous les tests s'appuient sur les mêmes comptes et les mêmes données : Camille (propriétaire), Sacha et Lou (comptes enfants), Sophie (amie proche), Théo (abonné), Nina (demande en attente), Marc (bloqué), Zoé (aucune relation), un compte suspendu et un administrateur.

## Périmètre de couverture

La couverture est mesurée avec PCOV sur le **code métier écrit pour l'application**. Le périmètre est défini dans `<source>` de [backend/phpunit.dist.xml](backend/phpunit.dist.xml) :

| Inclus | Rôle |
|---|---|
| `src/Service` | Scan IA des vêtements (`ClothingScanner`), stockage des photos (`ItemPhotoStorage`), `ScanFailedException` |
| `src/Security` | `UserChecker` : refus des comptes suspendus |
| `src/State` | `OwnerProcessor` : le créateur d'un logement ou d'un objet en devient propriétaire |
| `src/Doctrine` | `CurrentOwnerExtension` : chaque utilisateur ne voit que son contenu |
| `src/EventListener` | `ItemPhotoCleaner` : suppression du fichier photo avec l'objet |
| `src/Controller` | `/api/me`, `/api/scan`, `/api/items/{id}/photo` |
| `src/Repository/RelationshipRepository.php` | Seul repository qui contient une requête écrite à la main (`countReceived`) |

| Exclu | Pourquoi |
|---|---|
| `src/Entity` | Mapping Doctrine et accesseurs générés, sans logique. Les deux méthodes qui contiennent une règle métier (`User::isMinor`, `ShareLink::isActive`) ont quand même leurs tests unitaires. |
| `src/Enum` | Listes de valeurs, sans comportement. |
| `src/DataFixtures` | Données de test, pas du code de l'application. |
| Autres `src/Repository/*` | Générés par le maker : un constructeur et des exemples commentés. |
| `src/Kernel.php` | Fichier fourni par Symfony. |

Le but est de mesurer ce qui peut contenir une erreur de raisonnement : du code généré à 100 % ne prouverait rien.

## Résultat

| Suite | Tests | Assertions |
|---|---|---|
| Unit | 68 | 166 |
| Integration | 33 | 67 |
| Functional | 54 | 214 |
| **PHPUnit, total** | **155** | **447** |
| Cypress e2e | 13 | — |

```
Code Coverage Report:
 Summary:
  Classes: 100.00% (11/11)
  Methods: 100.00% (31/31)
  Lines:   100.00% (190/190)
```

Les 11 classes du périmètre sont à 100 % en lignes et en méthodes. Le rapport HTML est généré dans `backend/var/coverage/` par `make coverage`. Les 13 tests Cypress passent (`make e2e`).

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

### Integration (`backend/tests/Integration/`)

**`Repository/RelationshipRepositoryTest.php`** (10 tests). `countReceived()` sur la vraie base de test, par statut et par type.
- Camille : 3 relations acceptées, dont 2 abonnés et 1 amie proche ; 1 demande en attente ; 1 bloqué.
- Relation réciproque : Sophie a reçu une relation « amie proche » de Camille.
- Les demandes envoyées ne comptent pas : Théo a envoyé, il n'a rien reçu. Zoé n'a aucune relation.

**`Doctrine/CurrentOwnerExtensionTest.php`** (23 tests). L'extension est appliquée à de vraies requêtes, avec le vrai service `Security`.
- Pour chaque niveau (logement, pièce, rangement, catégorie, objet), Camille obtient la **liste exacte** de son contenu, perso et privé compris, sans le studio de son compte enfant.
- Sacha n'obtient que son studio. Théo, abonné sans contenu, et un anonyme n'obtiennent rien.
- En lecture d'un élément : Théo ne trouve pas la Maison, Sophie ne trouve pas une catégorie du garage, en passant par les jointures pièce > logement.
- Les ressources hors hiérarchie (les looks) ne sont pas filtrées.

### Functional (`backend/tests/Functional/`)

**`AuthenticationTest.php`** (12 tests).
- Connexion JWT réussie : le jeton obtenu ouvre `/api/me`.
- Mauvais mot de passe et compte inconnu : **401 avec le même message**, ce qui ne révèle pas si le compte existe.
- Compte suspendu : 401 avec « Ce compte est suspendu. ».
- Six routes protégées répondent 401 sans jeton, et un jeton invalide est refusé.
- La racine de l'API reste publique.

**`MeTest.php`** (5 tests). `/api/me` de Camille :
- 14 objets ;
- **2 abonnés** (Sophie, Théo ; Marc, bloqué, n'est pas compté) ;
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

**`ItemPhotoTest.php`** (14 tests). `/api/items/{id}/photo` :
- envoi : fichier écrit et URL enregistrée en base ;
- suppression : fichier et URL retirés ; une suppression sans photo répond aussi 204 ;
- objet d'un autre utilisateur ou objet inconnu : 404, en envoi comme en suppression, et la photo n'est pas modifiée ;
- six cas de 400 (refus par le contrôleur ou par `ItemPhotoStorage`) ;
- 413 au-delà de 3 Mo.

### E2E (`frontend/cypress/e2e/`)

| Spec | Tests | Parcours |
|---|---|---|
| `login.cy.js` | 4 | Connexion de Camille ; mauvais mot de passe (« Email ou mot de passe incorrect. ») ; **compte suspendu** (« Ce compte est suspendu. ») ; page protégée → connexion → retour à la page demandée |
| `navigation.cy.js` | 4 | Pièces de la Maison ; Dressing > Penderie (catégories et objets exacts) ; dépliage du Coffre à bijoux ; retour à l'accueil puis Grenier |
| `add-item.cy.js` | 3 | Ajout d'un « Pull rayé » « Au sale » dans Penderie › Hauts, toujours présent après rechargement ; liste des catégories proposées ; fermeture du formulaire sans ajout |
| `logout.cy.js` | 2 | Déconnexion depuis les paramètres du profil ; pages protégées inaccessibles ensuite |

Les éléments utilisés portent des attributs `data-cy`. Chaque test recharge les fixtures `test` avant de commencer : l'ajout d'objet, joué en premier, ne fausse pas la navigation, qui vérifie la liste exacte des objets.

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

## Contrôle par mutation

Une couverture de 100 % prouve que chaque ligne s'exécute, pas que les tests détectent une erreur. Des bugs plausibles ont donc été introduits à la main, un par un, puis le code a été restauré.

| Bug introduit | Résultat |
|---|---|
| `ItemPhotoStorage` : `> MAX_DIMENSION` devient `>=` (une image de 2048 px serait refusée) | Détecté (suite unit en échec) |
| `ShareLink::isActive` : `expiresAt > $at` devient `>=` (un lien resterait actif à l'instant de son expiration) | Détecté (suite unit en échec) |
| `ClothingScanner` : plus de nouvelle tentative sur les erreurs 5xx | Détecté (suite unit en échec) |
| `LoginView` (e2e) : retour à l'ancien message unique pour toute réponse 401 | Détecté (« refuse un compte suspendu » en échec) |

## Choix techniques

- **Horloge injectée.** `ClothingScanner` reçoit `Symfony\Component\Clock\ClockInterface` au lieu d'appeler `microtime()` et `usleep()`. En production, `NativeClock` attend réellement 1,5 s (mesuré : 1,507 s). En test, `MockClock` avance instantanément, ce qui permet de vérifier le budget de 50 s et les pauses sans ralentir la suite.
- **MockHttpClient.** Aucun test n'appelle OpenRouter. Les réponses (succès, 429, 5xx, HTML, erreur réseau) sont simulées, et les requêtes envoyées sont inspectées. Les tests fonctionnels remplacent le service par un vrai `ClothingScanner` branché sur ce client : le code testé est celui de la production.
- **`dama/doctrine-test-bundle`.** Chaque test PHPUnit s'exécute dans une transaction annulée à la fin : la base reste dans l'état des fixtures, et la suite peut être relancée indéfiniment.
- **Base de test séparée.** `.env.test` pointe vers le conteneur PostgreSQL, et Doctrine ajoute le suffixe `_test` : la base `penderie_db_test` est distincte de `penderie_db`. `.env.local` n'est pas lu en environnement de test.
- **En-tête `X-Penderie-Env: test`, réservé au Docker local.** Les tests Cypress utilisent l'application lancée sur `http://localhost:5173`. Un `cy.intercept()` ajoute cet en-tête à toutes les requêtes `/api` et `/uploads`. La configuration nginx ([docker/nginx/default.conf](docker/nginx/default.conf)) transmet alors `APP_ENV=test` à Symfony, qui utilise `penderie_db_test`. Sans l'en-tête, rien ne change.

  ⚠️ **Cette configuration ne doit jamais être utilisée en production** : n'importe qui pourrait envoyer l'en-tête et passer l'application en environnement de test, en mode debug et sur une autre base. Après les tests Cypress, la base de dev a été comparée à un instantané pris avant : elle est identique.
- **Journal du kernel en fichier en test.** Sans Monolog, Symfony écrit son journal sur la sortie d'erreur. En test, il est redirigé vers `backend/var/cache/test/test.log`, pour que les 404 attendues n'apparaissent pas comme des erreurs dans le terminal. Les échecs de test restent affichés par PHPUnit.

## Limites

- **Couverture des lignes seulement.** PCOV mesure les lignes exécutées, pas chaque branche d'une condition. Les cas limites listés plus haut compensent en partie, mais ne garantissent pas que toutes les combinaisons sont testées.
- **Contrôle par mutation manuel.** Quatre mutations ont été essayées à la main. Un outil comme Infection en essaierait des centaines.
- **Règles de visibilité pas encore testables.** Les scénarios de [docs/campagne-test.md](docs/campagne-test.md) (Théo voit la Penderie mais pas le carnet perso, Sophie voit le garage, liens de partage…) dépendent des Voters, qui ne sont pas écrits. Pour l'instant, `CurrentOwnerExtension` limite chacun à son propre contenu, et c'est ce comportement provisoire qui est testé.
- **Configuration API Platform non couverte.** Les expressions `securityPostDenormalize` (créer une pièce dans le logement d'un autre), les validations des entités et les opérations PATCH/DELETE générées ne sont pas incluses dans la mesure, et ne sont testées qu'indirectement.
- **OpenRouter jamais appelé.** Si le format de réponse réel change, les tests ne le verront pas.
- **Front partiellement couvert.** Il n'y a pas de tests unitaires des composants Vue. Les parcours e2e ne passent pas par le scan, l'envoi de photo depuis l'interface, le dressing ni la fiche objet.
- **E2E en local uniquement.** Les tests Cypress tournent sur la machine hôte contre le serveur de développement Vite. Aucun pipeline ne les lance automatiquement. Le rechargement des fixtures avant chaque test ajoute environ une seconde par test.
- **Photos des tests fonctionnels.** Elles sont écrites dans le vrai dossier `backend/public/uploads/items/`, puis supprimées par chaque test.
