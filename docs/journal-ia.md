# Journal d'utilisation de l'IA

Ce que l'IA m'a proposé, ce que j'ai choisi et pourquoi. Les décisions de conception détaillées sont dans [decisions.md](decisions.md).

---

## Étape 1 : configuration Docker et installation de Symfony

**Outil :** Gemini (assistant)

- **Proposition / question :** quelle version de Symfony installer.
- **Mon choix :** Symfony 7.4, au lieu de Symfony 7.0.
- **Pourquoi :** Composer bloquait l'installation de Symfony 7.0 à cause de failles de sécurité connues. La 7.4 est maintenue et ne pose pas ce problème.

---

## Étape 2 : création des entités

**Outil :** OpenCode (modèle Mimo)

Avant de créer les entités, l'IA m'a posé 5 questions pour cadrer le travail.

### 1. Collections inverses (OneToMany)
- **Question :** pour chaque relation ManyToOne, faut-il créer la collection en face (ex. `Lieu::pieces`, `Piece::rangements`, `Objet::reservations`), donc des relations bidirectionnelles, ou rester en ManyToOne unidirectionnel ?
- **Mon choix :** bidirectionnel complet.
- **Pourquoi :** c'est plus simple pour construire l'API, par exemple pour récupérer un logement avec toutes ses pièces.

### 2. Relation Membre ↔ Utilisateur
- **Question :** ma spécification disait ManyToOne (plusieurs membres pour un même utilisateur). L'IA proposait plutôt OneToOne, en voyant le membre comme un profil.
- **Mon choix :** garder le ManyToOne.
- **Pourquoi :** le but est d'avoir plusieurs sous-profils : un seul compte principal gère plusieurs utilisateurs (comptes enfants, colocataire…).

### 3. Champs énumérés (statut, visibilité, type de catégorie, type et statut de réservation)
- **Question :** backed enums PHP (`src/Enum/…`) ou chaînes simples de 50 caractères ?
- **Mon choix :** backed enums PHP.
- **Pourquoi :** meilleure garantie de sécurité et de cohérence : seules les valeurs prévues sont acceptées.

### 4. Migration
- **Question :** une migration existante créait une table `item` sans entité correspondante, et `lieu` n'avait pas de migration. Fallait-il supprimer cette migration orpheline et en régénérer une seule complète, ou garder l'existante et en ajouter une deuxième ?
- **Mon choix :** [À COMPLÉTER]
- **Pourquoi :** [À COMPLÉTER]

### 5. Nom de l'entité Objet
- **Question :** classe `Objet` / table `objet` (cohérent avec Rangement, Tenue…), ou `Item` / table `item` (cohérent avec la migration existante) ?
- **Mon choix :** [À COMPLÉTER : le code utilise aujourd'hui `Item`]
- **Pourquoi :** [À COMPLÉTER]

### Question complémentaire : repositories
- **Question :** une classe Repository par entité, ou seulement pour les principales ?
- **Mon choix :** [À COMPLÉTER]

---

## 08/11/2026 : conception du volet social (look du jour, relations, blocage)

**Outils :** Claude (conception, choix, relecture des plans) · Claude Code (analyse du code existant, plan technique, code)

### 1. Choix de la fonctionnalité à développer
- **Proposition de l'IA :** 4 pistes classées : droits et amis (recommandé, car tout le partage en dépend), lien de partage public, prêts, tenues.
- **Mon choix :** le look du jour (OOTD), puis l'aspect social complet.
- **Pourquoi :** c'est le cœur « réseau social » demandé par le client (partager son dressing avec ses abonnés). La fonctionnalité « Look » sera faite plus tard avec le prof.
- **Remarque :** l'IA avait d'abord compris « proposition de tenues (météo, goûts) ». J'ai corrigé : le look du jour est une publication, pas une suggestion automatique.

### 2. Analyse de l'existant
- **Demande à Claude Code :** lister ce qui existe déjà avant de coder.
- **Résultat :** les entités Relationship, Look, Comment, ShareLink existent en base, mais ne sont pas exposées dans l'API et n'ont aucun droit appliqué. Il n'y a pas encore de Voter.
- **Pourquoi cette étape :** ne pas recréer ce qui existe et partir de l'état réel du code.

### 3. Entité du look du jour
- **Options proposées :** A. une entité `Ootd` séparée (recommandé) · B. réutiliser `Look` en lui ajoutant une photo.
- **Mon choix :** A.
- **Pourquoi :** le Look sera travaillé avec le prof, donc je n'y touche pas pour éviter les conflits. De plus, un look du jour (un post daté avec photo) et un Look (une tenue composée) sont deux concepts différents.

### 4. Gestion du blocage
- **Options proposées :** A. une entité `Block` par personne (recommandé) · B. garder le statut `blocked` de Relationship.
- **Mon choix :** A.
- **Pourquoi :** avec B, bloquer quelqu'un qui avait deux relations (abonné et ami proche) en laissait une active, et on ne savait pas qui avait bloqué qui. Avec A, le blocage est clair, il s'applique partout, et débloquer est simple, comme le client le demande (« pouvoir débloquer quelqu'un »).

### 5. Nombre de looks du jour par jour
- **Options proposées :** A. un seul par jour (recommandé) · B. plusieurs.
- **Mon choix :** B, contre la recommandation.
- **Pourquoi :** une personne peut avoir une tenue de journée et une tenue de soirée.

### 6. Réactions (likes)
- **Options proposées :** A. un simple like (recommandé) · B. plusieurs réactions · C. plus tard.
- **Mon choix :** C.
- **Pourquoi :** priorité au look du jour et aux commentaires dans le temps disponible (54 h).

### 7. Visibilité d'un look du jour
- **Proposition de l'IA :** une visibilité choisie à chaque post (abonnés, amis proches, privé).
- **Mon choix :** rejetée, j'applique mon tableau des droits. Un look publié est visible par les comptes enfants, les amis proches, les abonnés, et par un inconnu via un lien. Un brouillon n'est visible que par moi.
- **Pourquoi :** cohérence avec le tableau des droits déjà validé. Et le client veut montrer ses tenues à ses 18 000 abonnés.

### 8. Objets identifiés dans un look du jour
- **Proposition de l'IA :** un objet n'est autorisé que si toutes les personnes qui voient le post peuvent aussi voir l'objet (une règle avec plusieurs niveaux).
- **Mon choix :** rejetée, trop compliquée. Ma règle : un objet privé ou perso (lui-même, ou son rangement, sa pièce ou son logement) ne peut pas être ajouté. Tous les autres le peuvent, et deviennent visibles via le look, sans leur emplacement.
- **Pourquoi :** c'est simple à comprendre pour le client, et conforme à mon tableau (ligne « look publié - objet » : visible par les abonnés). Publier son look revient à accepter de montrer ces vêtements, pas l'intérieur de sa maison.
- **À noter :** cette règle remplace l'ancienne (« objet perso masqué dans un look »).

### 9. Plan technique du lot 1 (Claude Code)
- **Proposition :** un découpage en 3 lots (relations, puis look du jour, puis commentaires) et un plan en 10 commits, avec les tests. Le premier Voter sera écrit au lot 2.
- **Mon choix :** validé, en partant de la branche `feature/test`, qui contient les tests et le Makefile.
- **Questions de Claude Code, et mes réponses :**
  1. Ami proche = abonné ? Deux relations séparées, mais **un ami proche a au moins les droits d'un abonné**. Sinon, un ami proche ne verrait pas ce qui est ouvert aux abonnés.
  2. Les mineurs sont exclus de la recherche et des demandes, pour les protéger.
  3. Un blocage renvoie une erreur 404, pour ne pas révéler qu'il existe.
  4. Un parent ne peut pas bloquer son enfant, et inversement : le lien familial prime.
  5. Pas de compteurs sur le profil public pour l'instant, pour rester minimal.

### Bilan
L'IA m'a fait gagner du temps sur l'analyse du code et la structuration des règles. J'ai rejeté ses propositions quand elles ajoutaient de la complexité (points 7 et 8) ou ne correspondaient pas au besoin (points 1 et 5). Mon tableau des droits reste la référence.

---

## 09/11/2026 : nom de l'application

**Outil :** Claude Code

- **Constat de l'IA :** l'audit avait relevé une incohérence de nom (la meta description affichait « Lendly », le reste « Penderie »). L'IA avait alors tout harmonisé en « Penderie ». En intégrant mon logo, elle a vu qu'il écrivait « LENDLY » et s'est arrêtée pour me demander quoi faire, au lieu de l'intégrer.
- **Mon choix :** l'application s'appelle **Lendly**. PENDERIE reste le nom du projet scolaire (dépôt, documentation, README).
- **Pourquoi :** Lendly est le nom de marque, celui de mon logo. Le nom du projet sert au cadre scolaire, pas à l'utilisateur. Les noms techniques (base, clés, en-têtes) gardent « penderie » pour ne rien casser.

---

## 14/11/2026 : lot 2, le look du jour (OOTD)

**Outil :** Claude Code

### 1. Où placer le fil des looks du jour
- **Options proposées par l'IA :** A. l'accueil devient le fil, et l'ancien accueil devient « Mon dressing » · B. garder l'accueil et renommer une entrée en « Looks » · C. une barre à 6 entrées.
- **Mon choix :** A. Barre : Accueil · Mon dressing · Scanner (au centre) · Amis · Profil.
- **Pourquoi :** comme sur les réseaux sociaux, on arrive sur ce que publient ses proches. Cinq entrées, avec le Scanner au centre.
- **Argument de l'IA retenu :** une barre à 6 entrées serait trop serrée sur mobile et le Scanner ne serait plus centré.

### 2. Plan technique du lot 2 (Claude Code)
- **Proposition :** routes, entité `Ootd` et migration, endpoints, extension de visibilité et `OotdVoter`, groupes de sérialisation, fixtures, matrice de tests (Sacha, Lou, Sophie, Théo, Nina, Marc bloqué, Zoé), écrans et 9 commits.
- **Mon choix :** validé.
- **Questions de Claude Code, et mes réponses :**
  1. La retouche de l'affichage ordinateur, non commitée, est d'abord commitée sur sa propre branche et fusionnée, avant de créer `feature/ootd`.
  2. Clic sur un objet d'un look : ma fiche complète pour mes objets, un panneau en lecture seule pour ceux des autres, **sans l'emplacement**.
  3. Photo obligatoire pour publier, brouillon possible sans photo, pas de retour en brouillon : on supprime.
  4. Dans le fil, seulement mes looks publiés.
  5. Date du look : aujourd'hui par défaut, passé possible, futur refusé.
  6. Les looks d'un profil public sont dans ce lot, avec la même règle de visibilité.
  7. Une seule cloche, dans l'en-tête du fil.
  8. 10 looks par page, avec « Voir plus ».
- **Pourquoi :** des règles simples, faciles à tester, et la confidentialité d'abord : un look ne montre jamais où sont rangés les objets.

### 3. Poster un look : la règle « partageable » lue par le front
- **Proposition de Claude Code :** déplacer la règle de `ItemExposure` dans `Item::isShareable()` et l'exposer dans `GET /api/items` (champ `shareable`), pour que le formulaire ne propose que les objets qu'un look peut montrer. `ShareableItems` revérifie toujours côté back.
- **Mon choix :** validé, et la décision est notée dans `decisions.md`.
- **Constat de l'IA en vérifiant :** `src/Entity` est hors du périmètre de couverture, donc le « 100 % » ne mesure plus la règle elle-même. Elle reste testée par la matrice de `ItemExposureTest` et par `OotdApiTest`, et des mutations (objet perso, logement privé) le confirment.
- **Autre constat :** aucun test e2e ne couvrait le Scanner, dont la caméra est maintenant partagée avec « Poster un look » (composable `useCamera`). Ajout de `scan.cy.js` : photo par la caméra, photo importée, caméra coupée en quittant l'écran. La réponse de l'IA est simulée.
- **Bug trouvé par une mutation :** en neutralisant la vérification « photo obligatoire » du front, les tests passaient encore, car le back renvoie le même message. Mais un brouillon était créé en douce. En creusant, l'IA a trouvé qu'une publication qui échoue après la création (envoi de la photo raté, par exemple) créait un brouillon de plus à chaque nouvel essai. Corrigé : l'écran retient l'identifiant du look créé et le met à jour ensuite. Deux tests e2e le vérifient (aucun envoi sans photo ; un seul look après deux essais).

### 4. Profil : « Mes looks / Brouillons · n »
- **Demande :** remplacer les onglets « Dressing / En vente » du profil par « Mes looks » et « Brouillons · n », les brouillons s'ouvrant sur `/looks/{id}/modifier` ; réactiver « Poster mon look » sur Mon dressing.
- **Choix de Claude Code :** aucune modification du back, `GET /api/me/ootds` suffit (une seule requête, triée côté front par date de publication). Sur mon profil, les objets portés ouvrent leur fiche. La vignette compacte d'objet (`ItemTile compact`, styles `thumb-*`), qui ne servait qu'aux anciens onglets, est supprimée.
- **Vérification :** `profile.cy.js` (6 tests) ; 4 mutations détectées (tri retiré, brouillons inversés, brouillon qui ouvre un nouveau look, bouton de Mon dressing désactivé).
- **Vérification demandée :** `GET /api/me/ootds` n'est pas paginé (`paginationEnabled: false`) : avec 15 looks, l'API les renvoie tous, sans lien `next`. Le profil reçoit donc tout et le compteur « Brouillons · n » est juste ; rien à changer.
- **Ajout demandé :** supprimer un look publié depuis mes looks (fil, profil), avec confirmation. `delete-look.cy.js` (5 tests) ; 3 mutations détectées (bouton sur le look d'une autre personne dans le fil, bouton sur un profil public, carte non retirée après suppression).
- **Limite relevée par l'IA :** dans le fil, supprimer un look déjà chargé décale la pagination : la page suivante (« Voir plus ») peut sauter un look. Le look sauté réapparaît au rechargement.

---

## 15/11/2026 : internationalisation (fr, en, de)

**Outil :** Claude Code

### 1. Plan i18n, back office et « Mon espace »
- **Proposition de Claude Code :** un plan en deux parties (i18n, puis back office et « Mon espace »), avec dix questions. Il avait constaté que le brief n'était pas dans le dépôt, que `User` n'avait ni langue, ni dernière connexion, ni route de modification, et que l'onglet « Inscription » ne marchait pas.
- **Mes réponses :**
  1. Le brief est ajouté dans `docs/brief.md` ; il sert de référence avec la consigne et `decisions.md`.
  2. Le scanner IA répond dans la langue de l'utilisateur.
  3. Catégories des fixtures : option A, un champ `labelKey` (options proposées : A. clé de traduction, B. ne rien traduire, C. table de traductions). Pièces et rangements restent du contenu utilisateur.
  4. Pas d'ESLint : un script Node suffit pour vérifier les clés.
  5. Sélecteur de langue sur l'écran de connexion : le brief veut qu'on puisse regarder sans compte, la langue des visiteurs compte aussi.
  6. Ajout de `lastLoginAt` pour mesurer les utilisateurs actifs.
  7. Admin : suspension seulement, pas de suppression de compte, pas de notification.
  8. Admin : seuls les objets visibles dans un look publié, des agrégats pour le reste (« le dressing doit être privé »).
  9. « Jamais portés » : looks publiés seulement.
  10. Export RGPD : JSON avec les URL des photos, sans ZIP.
- **Pourquoi l'allemand :** pas d'écriture de droite à gauche comme l'arabe, et je parle allemand (B1), donc je peux relire les traductions.
- **Inscription :** le brief la demande (« créer un compte »). Elle fera l'objet d'une branche à part, après l'i18n et avant le back office.
