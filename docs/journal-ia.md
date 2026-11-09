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
