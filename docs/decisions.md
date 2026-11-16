# Journal de décision

Décisions de conception prises au fil du projet, avec leur justification. Une décision qui change est corrigée ici, avec sa date, plutôt que supprimée.

Voir aussi le [journal d'utilisation de l'IA](journal-ia.md) : ce que l'IA a proposé, ce qui a été retenu et pourquoi.

## 2026-11-08 · Volet social

Décisions prises à partir du tableau des droits, avant le développement du volet social en trois lots :

| Lot | Branche | Contenu |
|---|---|---|
| 1 | `feature/social-relations` | blocage, `RelationshipChecker`, API des relations et des blocages, profil public, recherche, page Amis |
| 2 | `feature/ootd` | entité `Ootd` et sa photo, `OotdVoter`, fil des OOTD, écrans pour poster et consulter |
| 3 | `feature/ootd-comments` | commentaires sur les OOTD |

Les réactions (likes) et le lien public d'un OOTD pour les visiteurs sans compte viendront plus tard.

### OOTD (tenue du jour)

| Décision | Justification |
|---|---|
| Nouvelle entité `Ootd`, séparée de `Look`. Champs : `owner`, `photo`, `caption`, `wornOn` (date), `publishedAt`, `createdAt`, objets portés. Plusieurs OOTD par jour autorisés. | `Look` sera retravaillé plus tard avec l'enseignant : on n'y touche pas. Un OOTD est une publication datée, un look une composition réutilisable. |
| Pas de champ `visibility`. Brouillon (`publishedAt` vide) : visible par le propriétaire seul. Publié : visible par le propriétaire, ses comptes enfants (+18 et -18), ses amis proches acceptés et ses abonnés acceptés. | Une règle unique, simple à comprendre et à tester. Un bloqué, une demande en attente ou un inconnu ne voient rien. |
| Le lien public vers un OOTD (`ShareLink` étendu) viendra dans un lot suivant. | Garder le lot 2 centré sur les comptes connectés. |

### Objets identifiés dans un OOTD

| Décision | Justification |
|---|---|
| Seulement mes propres objets. | On ne peut pas exposer l'objet de quelqu'un d'autre. |
| Refus (422) d'un objet privé ou perso, que ce soit l'objet lui-même ou son rangement, sa pièce ou son logement (`visibility` = `private` ou `isPersonal` = vrai). | Publier un OOTD ne doit pas faire sortir un objet volontairement caché. |
| Les autres objets sont autorisés et deviennent visibles via l'OOTD, même pour un abonné qui n'a pas accès à la pièce. Sont exposés : photo, nom, catégorie, marque. Jamais l'emplacement (logement, pièce, rangement). | L'utilisateur choisit de montrer l'objet, pas l'endroit où il le range. |
| L'écran de choix ne propose que les objets autorisés, et le back revérifie. | Le front guide, le back garantit. |
| Un objet devenu privé ou perso après la publication est masqué à l'affichage pour les autres. | La confidentialité actuelle l'emporte sur l'état au moment de la publication. |

### Commentaires sur un OOTD

| Qui | Droits |
|---|---|
| Propriétaire | tout, y compris supprimer n'importe quel commentaire de son OOTD |
| Comptes enfants +18, amis proches acceptés | écrire, modifier et supprimer leurs propres commentaires |
| Comptes enfants -18, abonnés, inconnus | rien |

Justification : le commentaire est réservé au cercle proche. Un abonné peut regarder, pas commenter. Un mineur ne commente pas.

### Relations et blocage

| Décision | Justification |
|---|---|
| Une demande (abonné ou ami proche) est créée `pending`. Le demandeur est toujours l'utilisateur connecté. Refusé : une demande à soi-même, un doublon, une demande quand un blocage existe. | Le serveur décide du demandeur et du statut, jamais le client. |
| Seul le destinataire accepte (`pending` → `accepted`). Refuser = supprimer la demande. | Pas de statut « refusé » à gérer : une demande refusée disparaît. |
| Nouvelle entité `Block` (`blocker`, `blocked`, unique). Effet dans les deux sens et partout : fil, profil, commentaires, recherche, nouvelles demandes. Bloquer supprime les relations existantes entre les deux personnes. Débloquer = supprimer le blocage, sans restaurer les relations. | Le statut `blocked` de `Relationship` bloquait un seul type de relation : bloquer quelqu'un qui en avait deux laissait l'autre active. Un blocage vise une personne, pas une relation. |
| Le statut `blocked` de `Relationship` n'est plus utilisé. Les relations bloquées existantes sont converties en `Block` par la migration. | Une seule source de vérité pour le blocage. |

### Choix complémentaires (lot 1)

| # | Décision | Justification |
|---|---|---|
| 1 | Ami proche et abonné restent deux relations séparées, mais un ami proche accepté a au moins les droits d'un abonné : `RelationshipChecker::isFollower(viewer, owner)` est vrai aussi si `viewer` est ami proche accepté de `owner`. | Le cercle des amis proches est inclus dans celui des abonnés. Sans cette règle, un ami proche qui ne s'est pas aussi abonné verrait moins de choses qu'un simple abonné. |
| 2 | Les comptes -18 sont exclus de la recherche et des demandes (404). | Protection des mineurs : personne ne peut les trouver ni les solliciter. Ils restent liés à leur parent. |
| 3 | Un blocage répond 404 (profil, recherche, demande), jamais 403. | Ne pas révéler à quelqu'un qu'il a été bloqué : il voit la même chose que pour un compte inexistant. |
| 4 | Un parent ne peut pas bloquer son compte enfant, et un enfant ne peut pas bloquer son parent (422). | Le lien parent-enfant donne des droits qu'un blocage contredirait, et le parent reste responsable du compte enfant. |
| 5 | Pas de compteurs (abonnés, amis proches) sur le profil public dans ce lot. | Profil public minimal : pseudo, avatar, bio et lien avec moi. Les compteurs pourront être ajoutés plus tard. |
| — | Le profil public n'expose jamais l'email, le mot de passe, les rôles, la date de naissance, le compte parent ni aucune adresse. | Données personnelles : seul le groupe de sérialisation `user:public` (`id`, `username`, `avatar`, `bio`) est utilisé pour un autre utilisateur. |
| — | Aucun Voter dans ce lot : les règles passent par des expressions `security`, des processors et des extensions Doctrine. `OotdVoter` (lot 2) sera le premier Voter du projet. | Les règles du lot 1 portent sur une seule entité à la fois et s'expriment simplement ; le Voter devient utile avec la visibilité des OOTD. |

## 2026-11-09 · Nom de l'application

| Décision | Justification |
|---|---|
| L'application s'appelle **Lendly** : titre de l'onglet, meta description, logo de l'écran de connexion, titre de l'API (`Lendly API`), en-tête envoyé au service d'IA, nom du paquet Composer (`thaisflx/lendly-backend`). | C'est le nom de marque, celui que voit l'utilisateur. Il correspond au logo dessiné pour l'application. |
| **PENDERIE** reste le nom du projet scolaire (module WR506D) : dépôt, dossiers, documentation, README. Les noms techniques ne changent pas : base `penderie_db`, clé `penderie_token` du navigateur, en-tête `X-Penderie-Env`, comptes de démo `@penderie.test`. | Le nom du projet et celui du produit sont deux choses distinctes. Renommer les noms techniques n'apporterait rien à l'utilisateur et casserait les sessions, les bases et la configuration existantes. |

## 2026-11-11 · Refonte visuelle (maquette v2)

Référence : [`docs/maquette-v2/`](maquette-v2/README.md), validée (mobile 390 px).

| Décision | Justification |
|---|---|
| Titres en **Mono Scribble** (démo), avec Bricolage Grotesque 800 en secours ; texte courant en Figtree. Une majuscule donne une lettre manuscrite, une minuscule une capitale grasse : les grands titres de page prennent une majuscule initiale, les titres de section et noms de cartes s'écrivent en minuscules. | C'est l'identité de la maquette. La règle d'écriture évite qu'un titre soit entièrement manuscrit, donc illisible. |
| `unicode-range` exclut les chiffres (U+0030-0039) de Mono Scribble. | La démo n'a pas de chiffres : elle affiche un logo à leur place. Les chiffres passent en Bricolage Grotesque. |
| Logo en texte (`AppLogo.vue`) : `len` et `l` roses, `D` et `Y.` bordeaux, `role="img"` et `aria-label="Lendly"`. | Pas d'image à charger ; la même police que les titres. Le rose (1,7:1 sur le fond) est accepté car un logo est exempté du critère de contraste (WCAG 1.4.3) et le nom est donné par `aria-label`. |
| Favicon : le « Y. » manuscrit bordeaux sur un carré arrondi rose pâle, en tracés extraits du `.ttf` avec fontTools (1,2 Ko). | Un SVG qui chargerait la police ne s'afficherait pas comme favicon ; les tracés ne dépendent de rien. |
| Violet foncé `#5E6CA8` pour l'élément actif (texte blanc, 5:1) ; texte indicatif des champs en `#7D6F80` (4,7:1 sur blanc). | Tous les textes respectent 4.5:1. |
| Pas de ligne décorative violette, seulement des étoiles, avec parcimonie. | Choix visuel : les lignes de la maquette alourdissent les écrans. |
| Les polices testées (`fonts/` à la racine, `defante.otf`) sortent du dépôt ; `/fonts/` est ignoré. Les HTML de la maquette pointent vers `frontend/src/assets/fonts/MonoScribble.ttf`. | Une seule copie de la police dans le dépôt, celle qu'utilise l'application. |

## 2026-11-14 · Lot 2 : look du jour (OOTD)

Complète les règles de la section « Volet social » (2026-11-08), qui restent valables.

### Navigation

| Décision | Justification |
|---|---|
| Barre de navigation : **Accueil · Mon dressing · Scanner (centre) · Amis · Profil**. L'entrée « Looks » grisée disparaît. | Cinq entrées, Scanner au centre : la barre garde sa forme. |
| **Accueil (`/`) = fil des looks du jour.** | Comme sur les réseaux sociaux : on arrive sur ce que publient ses proches. |
| **Mon dressing (`/dressing`)** = l'ancien accueil (logement, pièces, carte « look du jour »). **Tous mes objets (`/dressing/objets`)** = l'ancienne page Dressing, avec ses filtres Tout / En vente / Prêtés, ouverte depuis Mon dressing. | Rien n'est perdu : les objets « En vente » du profil restent accessibles ici. |
| Poster un look : `/looks/nouveau` ; reprendre un brouillon : `/looks/{id}/modifier`. Plein écran, sans barre. | Même logique que le Scanner. |
| La cloche des demandes d'amis est dans l'en-tête du fil, et retirée de Mon dressing. | Une seule cloche, sur l'écran d'arrivée. |
| Profil : onglets **« Mes looks »** (publiés, du plus récent au plus ancien, sans en-tête d'auteur, mes objets ouvrent leur fiche) et **« Brouillons · n »** (vignettes qui s'ouvrent sur `/looks/{id}/modifier`). Ils remplacent « Dressing / En vente ». Une seule requête : `GET /api/me/ootds`, triée côté front. | Le profil montre ce que je publie ; les objets restent dans Dressing → Tous mes objets. Le compteur de brouillons rappelle ce qui reste à finir. |
| La carte « look du jour » de Mon dressing ouvre `/looks/nouveau`. | L'écran existe depuis le commit précédent : le bouton n'est plus grisé. |
| Supprimer un look publié : bouton corbeille discret sur **mes** looks seulement (fil, onglet « Mes looks »), confirmation « Supprimer ce look ? Sa photo sera effacée. », puis la carte disparaît sans recharger. Jamais sur le look d'une autre personne, ni sur un profil public. | Applique la règle « pas de retour en brouillon, on supprime le look ». Le back refuse déjà la suppression aux autres (`OotdVoter`) : le bouton absent évite seulement une action impossible. |

### Règles techniques

| Décision | Justification |
|---|---|
| Visibilité appliquée par une extension Doctrine (`OotdVisibilityExtension`) sur le fil, le détail et les looks d'un profil : un OOTD invisible n'est jamais trouvé (404). `OotdVoter` (premier Voter du projet) contrôle aussi la lecture et réserve la modification et la suppression au propriétaire. | 404 plutôt que 403 : ne pas révéler l'existence d'un brouillon ni d'un blocage (comme au lot 1). |
| L'extension et `RelationshipChecker::canSeeOotdOf` sont testées l'une contre l'autre sur toute la matrice. | La règle est écrite deux fois (DQL et PHP) : le test garantit qu'elles disent la même chose. |
| `CurrentOwnerExtension` ne s'applique pas à `Ootd`. | Le fil montre aussi les looks des autres. |
| Les objets d'un OOTD sont exposés avec le groupe `ootd:item` : identifiant, nom, photo, catégorie, marque. Jamais le rangement, la pièce ni le logement. Un objet devenu privé ou perso après la publication est retiré de l'affichage pour les autres (pas pour le propriétaire). | Montrer le vêtement, pas l'intérieur de la maison. |
| Photo d'OOTD : même logique que les objets (JPEG en base64, taille maximale, nom aléatoire), dans `uploads/ootds/`. Le fichier est supprimé avec l'OOTD. | Un seul service de stockage des photos pour les deux. |
| Clés d'attributs en anglais, alignées sur le scanner : `brand`, `size`, `color`, `composition`, `type`, `description` (et `artist`, `year` pour les vinyles). Les fixtures test ont été alignées. | Une seule convention pour le scanner, les fixtures et l'API : la marque d'un objet se lit toujours dans `brand`. |
| Un objet qui ne m'appartient pas, ajouté à un look, répond **400 « introuvable »** et non 422. | `CurrentOwnerExtension` rend les objets des autres introuvables avant même la validation : l'API ne révèle pas qu'ils existent. La contrainte `ShareableItems` garde le contrôle en défense. |
| Les objets d'un look sont affichés dans un ordre stable (ordre de création des objets). | Sans ordre explicite, la base peut les renvoyer dans n'importe quel ordre. |
| Un look publié garde sa photo : on ne peut que la remplacer ou supprimer le look ; la retirer répond 422. | Conséquence de la photo obligatoire pour publier : un look publié sans photo ne doit jamais exister. |
| La règle « partageable » (un objet peut-il apparaître dans un look) vit dans **`Item::isShareable()`**. Elle est exposée au front dans le champ `shareable` de `GET /api/items`, et l'écran « Poster un look » ne propose que ces objets. Le back la revérifie toujours avec la contrainte `ShareableItems`. `ItemExposure` se contente d'y renvoyer pour rester injectable (`ShareableItemsValidator`, `OotdNormalizer`). | Une seule règle, lue au même endroit par l'API et par le front : le formulaire ne propose rien que l'API refuserait. Le filtre du front n'est qu'un confort, la sécurité reste côté back. Comme `src/Entity` est hors du périmètre de couverture, la règle est testée par la matrice de `ItemExposureTest`, par `OotdApiTest` et par des mutations. |

### Réponses aux questions du plan

| # | Décision | Justification |
|---|---|---|
| 1 | Clic sur un objet d'un look : **mes objets** ouvrent leur fiche complète ; **ceux des autres** ouvrent un panneau en lecture seule (photo, nom, catégorie, marque, jamais l'emplacement). | La fiche d'un objet est réservée à son propriétaire (404 pour les autres). |
| 2 | **Photo obligatoire pour publier** (422 sinon). Un brouillon peut rester sans photo. **Pas de retour en brouillon** après publication : on supprime le look. | Un look du jour, c'est d'abord une photo. Une seule action pour retirer un look : plus simple à comprendre et à tester. |
| 3 | Dans le fil, **seulement mes looks publiés**. Mes brouillons sont dans mon profil (onglet « Brouillons »). | Le fil montre ce que les autres voient. |
| 4 | `wornOn` : **aujourd'hui par défaut**, une date passée est acceptée, **une date future est refusée (422)**. | On peut poster la tenue de la veille, pas celle de demain. |
| 5 | **Looks d'un profil public** dans ce lot : `GET /api/users/{id}/ootds`, avec la même extension, affichés sur le profil public. | Même règle de visibilité que le fil, sans code de droits en plus. |
| 6 | Fil : **10 looks par page**, avec un bouton « Voir plus ». | Pas de défilement infini : plus simple, accessible au clavier, et l'utilisateur garde la main. |

## 2026-11-15 · Internationalisation (i18n)

Consigne des chapitres 22-23 : site entièrement multilingue, tout adapté à la locale. Brief : [brief.md](brief.md).

| Décision | Justification |
|---|---|
| Trois langues : **français**, **anglais** et **allemand**. Le **français est la langue par défaut et la langue de secours partout** : côté back (`default_locale: fr`, `fallbacks: [fr]` de Symfony Translation), côté front (`locale` et `fallbackLocale: 'fr'` de vue-i18n). Une traduction manquante en anglais ou en allemand s'affiche donc en français, jamais en anglais. | Le français est la langue du client et de ses abonnés ; l'anglais élargit l'audience de son compte de seconde main. |
| **L'allemand plutôt que l'arabe** pour la troisième langue. | L'arabe s'écrit de droite à gauche : toute la mise en page (barre de navigation, cartes, panneaux) serait à retourner, pour un projet de 54 h. Je parle allemand (niveau B1) : je peux relire les traductions moi-même. |
| Ordre de choix de la langue côté API : **`User.locale`** si la personne est connectée, sinon l'en-tête **`Accept-Language`** ramené à fr, en ou de (`de-CH` → `de`, une langue non gérée → `fr`), sinon **`fr`**. | La langue choisie sur le compte suit la personne sur tous ses appareils. Le brief demande de pouvoir regarder **sans compte**, via un lien public : la langue des visiteurs anonymes compte donc aussi, d'où l'en-tête. |
| La langue est enregistrée sur le compte (`User.locale`, `fr` par défaut, valeurs `fr`, `en`, `de`) et modifiée par `PATCH /api/me`, qui n'accepte que ce champ. Avant la connexion, un sélecteur sur l'écran de connexion garde le choix dans le navigateur et l'envoie dans `Accept-Language`. | Une seule route, limitée à ce qu'on peut changer : pas de modification d'email ni de rôles par ce biais. |
| Messages de l'API (validations, erreurs) traduits par Symfony Translation (domaines `validators`, `messages`, `security`). Les libellés des enums (visibilité, statut d'un objet, type de catégorie, relations) ont leurs traductions, pour Twig et le back office. L'API continue d'envoyer les **valeurs** (`for_sale`), que le front traduit. | Les valeurs sont stables et testables ; seul l'affichage change de langue. |
| Le front reconnaît les erreurs par leur **code HTTP**, plus par leur texte (ex. connexion refusée : 401, au lieu du texte « Invalid credentials. »). | Une fois traduit, le texte change selon la langue : s'y fier casserait l'écran de connexion. |
| Les **contenus saisis par les utilisateurs ne sont pas traduits** : noms des objets, logements, pièces et rangements, légendes, bio. Les pièces et rangements des fixtures restent du contenu utilisateur. | Ce sont les mots de la personne, pas ceux de l'application. |
| **Catégories des fixtures : champ `Category.labelKey`** (vide par défaut), ex. `category.tshirts`. S'il est rempli, l'application affiche sa traduction ; sinon le nom saisi. Renommer la catégorie efface la clé. | Les catégories proposées par défaut parlent la langue de l'utilisateur, celles qu'il crée restent telles quelles. Une seule colonne, plutôt qu'une table de traductions (trop lourde pour des données de démo). |
| Le **scanner IA répond dans la langue de l'utilisateur** (nom, couleur, description…). | Consigne : tout doit être adapté à la locale. Le résultat reste un brouillon que l'utilisateur corrige avant d'ajouter. |
| Dates, nombres et dates relatives (« hier », « yesterday », « gestern ») formatés par **`Intl`** dans la langue choisie ; « à l'instant » reste une traduction (`Intl` dirait « maintenant »). L'attribut `lang` de la page, son titre et sa description suivent la langue. | Pas de format écrit à la main par langue ; `lang` sert aux lecteurs d'écran et à la césure. |
| Contrôle des traductions : un test PHPUnit (back) et un script Node sans dépendance (front, `make i18n-check`) échouent s'il manque une clé en `en` ou `de` par rapport à `fr`. Pas d'ESLint. | Une clé oubliée afficherait la clé brute à l'écran : on veut le savoir avant. |
| Limite acceptée : le calendrier natif de `<input type="date">` suit la langue du **navigateur**, pas celle de l'application. | Comportement du navigateur, non modifiable sans recoder un calendrier. |

## 2026-11-16 · Back office et « Mon espace »

Consigne des chapitres 22-23 : un back office pour le commanditaire (administrer toute l'application, avec des KPI pertinents) et un espace pour l'utilisateur. Brief : « Faire un admin pour gestion de l'application ».

### Back office (`/admin`)

| Décision | Justification |
|---|---|
| **EasyAdmin 4** sous **`/admin`**, servi par Symfony (port 8000 en local), réservé à **`ROLE_ADMIN`**. | Le client « ne connaît rien en informatique » et veut quelque chose de « très très simple » : EasyAdmin donne des listes, des fiches et des boutons standard, sans écran à concevoir. |
| **Firewall `admin` séparé, avec session** et formulaire de connexion (`form_login`, jeton CSRF), à côté du firewall `api` (JWT, sans état), inchangé. Un jeton JWT n'ouvre pas `/admin`, une session admin n'ouvre pas l'API. Les comptes suspendus sont refusés (`UserChecker`). | Le back office est un site classique (pages, formulaires) : la session et le CSRF sont la protection adaptée. Séparer les firewalls évite qu'une faille de l'un ouvre l'autre. |
| **Suspension seulement, pas de suppression de compte.** Un admin ne peut ni se suspendre ni retirer son propre `ROLE_ADMIN`. Pas de modification du mot de passe, de l'email ni du profil. | Une suspension se défait, une suppression non. Se retirer ses propres droits bloquerait l'administration. |
| **Pas de notification** quand un look est supprimé par la modération. | Réponse au plan : l'application n'a pas de notifications aujourd'hui. |
| Le back office est **traduit** (fr, en, de) : textes propres dans le domaine `admin`, ceux d'EasyAdmin fournis par le bundle. Langue : celle du compte de l'admin, comme dans l'API. | Consigne i18n : tout est adapté à la locale. |
| Libellés **simples, sans jargon** (« Personnes venues ces 30 derniers jours » plutôt que « MAU »). | Brief : le client ne connaît rien en informatique, « pas de jargon ». |

### Ce que l'admin voit (RGPD)

Brief : « **Le dressing doit être privé** », certaines personnes seulement y ont accès. L'admin n'en fait pas partie : il gère l'application, il n'a pas besoin du contenu des dressings.

| L'admin voit | Pourquoi |
|---|---|
| **Des chiffres globaux** (KPI) pour tout ce qui est privé : nombres, moyennes, répartitions, jamais le détail. | Minimisation des données (RGPD, art. 5.1.c) : des agrégats suffisent pour piloter l'application. |
| Les **looks publiés** (photo, légende, objets portés, sans leur emplacement), pour les modérer (supprimer). | Ce contenu est déjà montré à d'autres personnes : c'est lui qui peut poser problème. |
| Les **objets visibles dans un look publié** seulement (nom, photo, type, marque, propriétaire, nombre de looks), en lecture seule. | Même règle que pour les autres utilisateurs (decisions.md, lot 2) : un objet n'est exposé qu'à travers un look, jamais son emplacement. |
| Pour gérer les comptes : pseudo, email, rôles, suspension, date d'inscription, dernière connexion, mineur ou non, nombre d'objets. | Nécessaire pour suspendre un compte ou donner le rôle admin. La case « mineur » remplace la date de naissance. |
| Les **catégories** : nom, type et nombre d'objets, modifiables ; suppression seulement si la catégorie est vide. Pas de création. | Une catégorie appartient à un rangement d'un utilisateur : la supprimer pleine supprimerait ses objets, en créer une chez lui n'a pas de sens. |

| L'admin ne voit jamais | Pourquoi |
|---|---|
| Les adresses des logements, les noms des pièces et des rangements. | Le dressing doit être privé. |
| Les brouillons, les objets perso ou privés, les objets jamais montrés dans un look. | Visibles par leur propriétaire seul (decisions.md, lot 2). |
| Les relations et les blocages détaillés (qui suit qui, qui bloque qui). | Seuls les totaux servent les KPI. Un blocage ne doit jamais être révélé. |
| La date de naissance, le mot de passe. | Inutiles pour administrer. |

### KPI du tableau de bord

| KPI (libellé à l'écran) | Source | Pourquoi |
|---|---|---|
| Nouveaux comptes (7 et 30 derniers jours, et par semaine) | `User.createdAt` | Croissance |
| Personnes venues (7 et 30 derniers jours) | **`User.lastLoginAt`**, mis à jour à chaque connexion (API et back office) | Distingue les comptes vivants des comptes inscrits. Le jeton dure 1 h : une visite régulière se reconnecte souvent |
| Looks publiés par semaine | `Ootd.publishedAt` | Usage de la fonction sociale principale |
| Objets rangés par personne (médiane et moyenne) | `Item` | Adoption de l'inventaire ; la médiane n'est pas faussée par quelques gros comptes |
| Part des objets montrables | Règle d'`Item::isShareable`, reprise en requête (testée contre la méthode, objet par objet) | Montre à quel point les utilisateurs gardent leurs objets privés |
| Demandes d'amis acceptées (nombre et taux) | `Relationship` (acceptées / acceptées + en attente) | Santé du réseau |
| Objets par type de catégorie | `Category.type` (libellé traduit) | Les noms de catégories sont libres, donc non agrégeables ; le type est fiable |
| Comptes suspendus | `User.isSuspended` | Suivi de la modération |

Rien sur les prêts (lot 3). Pas d'« objets ajoutés par période » : `Item` n'a pas de date de création.

### « Mon espace » (utilisateur)

| Décision | Justification |
|---|---|
| Écran **`/espace`**, ouvert depuis le Profil. | Consigne : un espace avec des informations sur son espace. |
| `GET /api/me/stats` calcule côté back : objets par catégorie et par logement, objets jamais portés (looks **publiés** seulement), 5 objets les plus portés, looks publiés, abonnés et abonnements. | Réponses au plan. Un seul appel, des nombres déjà prêts : l'écran reste simple. |
| Choix de la langue dans « Mon espace » (`PATCH /api/me`). | Plan validé. |
| `GET /api/me/export` : fichier **JSON** à télécharger avec toutes **mes** données (profil, logements et objets, looks avec brouillons, relations avec le seul pseudo de l'autre personne, comptes que j'ai bloqués, mes commentaires). Les photos y sont des **URL**, sans ZIP. | Droit d'accès et à la portabilité (RGPD, art. 15 et 20). Rien sur les autres personnes au-delà de leur pseudo. |
| Suppression du compte : **seulement proposée** (plan d'action d'audit.md). | Réponse au plan. |
