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
