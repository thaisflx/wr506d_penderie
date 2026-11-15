# Campagne de test — jeu de données

Chargement : `make fixtures-test` (ou `php bin/console doctrine:fixtures:load --group=test -n`)
Mot de passe de tous les comptes : `password`

## Comptes

| Compte | Rôle dans le tableau des droits |
|---|---|
| `camille@test.local` | Propriétaire |
| `sacha@test.local` | Compte enfant +18 de Camille |
| `lou` (sans email) | Compte enfant -18 de Camille, géré par le parent, ne se connecte pas |
| `sophie@test.local` | Amie proche acceptée (+ abonnée) |
| `theo@test.local` | Abonné accepté |
| `nina@test.local` | Demande d'ami proche **en attente** → traitée comme un random |
| `marc@test.local` | Abonné accepté, **à bloquer** : les tests le bloquent eux-mêmes (entité `Block`), aucun blocage dans les fixtures |
| `zoe@test.local` | Random, aucune relation |
| `suspendu@test.local` | Compte suspendu → connexion refusée |
| `admin@test.local` | Administrateur |

## Logements de Camille

| Logement | Pièce | Rangement | Visibilité | Contenu |
|---|---|---|---|---|
| Maison (Abonnés) | Dressing (Abonnés) | Penderie (Abonnés) | ouvert | T-shirt, jean (au sale), baskets (réservées), **carnet intime (perso)** |
| | | Coffre à bijoux (**perso**) | fermé | Bague de grand-mère |
| | Grenier (Abonnés) | Carton « Vinyles de papa » (Abonnés) | ouvert | Abbey Road, Led Zeppelin IV, Thriller (en vente) |
| | | Carton « Déco de Noël » (Privé) | fermé | Guirlande |
| | Garage (Amis proches) | Caisse à outils (Amis proches) | amis proches | Perceuse (prêtée), ponceuse |
| | Bureau (Privé) | Étagère (Privé) | fermé | Contrat de location |
| | Collection (**pièce perso**) | Vitrine | fermé | Montre de collection |
| Garde-meuble (Privé) | Box 14 | Cartons | fermé | Manteau (vendu à Sophie) |

Le compte enfant Sacha a son propre studio privé (Hoodie gris).

## Autres données

- **Looks (entité `Look`, avant le lot 2)** : « Look du dimanche » publié (contient le carnet perso, qui doit rester masqué) ; « Brouillon soirée » non publié. Les looks du jour du lot 2 (entité `Ootd`) sont décrits plus bas.
- **Commentaires** : Sophie sur le look, Sacha sur les baskets
- **Prêts** : perceuse à Sophie (en cours), ponceuse à Sacha (en retard), Abbey Road à Sophie (terminé)
- **Ventes** : Thriller en vente ; manteau vendu à Sophie
- **Liens de partage** : look (actif), sélection de vinyles (expire dans 30 jours), T-shirt (expiré), baskets (révoqué)

## Scénarios attendus (exemples)

| Qui | Action | Résultat attendu |
|---|---|---|
| Théo (abonné) | voir la Penderie | ✅ T-shirt, jean, baskets — ❌ carnet intime |
| Théo (abonné) | voir le Garage | ❌ refusé (amis proches seulement) |
| Sophie (amie proche) | voir le Garage | ✅ |
| Sophie | commenter le look | ✅ |
| Théo | commenter le look | ❌ refusé |
| Nina (en attente) | voir la Maison | ❌ comme un random |
| Marc, une fois bloqué par Camille | voir quoi que ce soit, la trouver, lui envoyer une demande | ❌ (404, rien ne révèle le blocage) |
| N'importe qui sauf Camille | voir le Coffre à bijoux, la Collection, le Bureau | ❌ |
| Zoé (random) | ouvrir le lien de la sélection | ✅ aperçu seulement |
| Zoé | ouvrir le lien expiré ou révoqué | ❌ |
| `suspendu@test.local` | se connecter | ❌ « Ce compte est suspendu. » |
| Camille | voir l'adresse de la Maison | ✅ (personne d'autre) |

## Volet social : relations et blocage (lot 1)

Règles détaillées dans [decisions.md](decisions.md). Les droits sur les looks du jour sont testés depuis le lot 2 (ci-dessous) ; les commentaires viendront au lot 3.

| Qui | Action | Résultat attendu |
|---|---|---|
| Zoé | chercher « cam », envoyer une demande d'abonnement à Camille | ✅ demande en attente |
| Camille | accepter ou refuser la demande de Nina | ✅ (seule la destinataire peut accepter) |
| Nina | accepter sa propre demande | ❌ 403 |
| Théo | voir ou accepter la demande de Nina | ❌ 404 |
| N'importe qui | chercher Lou (-18) ou le compte suspendu | ❌ absents de la recherche |
| Camille | bloquer Marc | ✅ ses relations avec Marc sont supprimées ; ils ne se voient plus |
| Camille | bloquer Sacha ou Lou ; Sacha, bloquer Camille | ❌ 422 (parent ↔ enfant) |
| Marc | voir le blocage posé par Camille | ❌ 404 |
| Camille | débloquer Marc | ✅ visible de nouveau, abonnement non rétabli |

## Volet social : looks du jour (lot 2)

Règles détaillées dans [decisions.md](decisions.md) (sections « Volet social » et « Lot 2 »). Données : `TestOotdFixtures`, toutes avec la photo de démo `uploads/demo/t-shirt-blanc.jpg` (jamais effacée par une suppression).

| Look | Propriétaire | État | Objets portés |
|---|---|---|---|
| « Tenue de bricolage du samedi » | Camille | publié hier | T-shirt blanc, Ponceuse (du Garage, amis proches) |
| « Jean brut et rien d'autre » | Camille | publié il y a 3 jours | Jean brut |
| « Idée pour la soirée » | Camille | **brouillon**, sans photo | Baskets montantes |
| « Prête pour le marché » | Sophie | publié il y a 2 h | aucun |

| Qui | Action | Résultat attendu |
|---|---|---|
| Camille | ouvrir le fil | ✅ le look de Sophie puis les siens, du plus récent au plus ancien ; ❌ pas son brouillon |
| Sacha, Lou (enfants), Sophie (amie proche), Théo (abonné) | voir les looks publiés de Camille | ✅ |
| Nina (en attente), Zoé (inconnue), Marc une fois bloqué | voir un look de Camille | ❌ 404, comme s'il n'existait pas |
| N'importe qui sauf Camille | voir son brouillon | ❌ 404 |
| Théo | voir la Ponceuse dans le look | ✅ nom, photo, catégorie, marque ; ❌ jamais son emplacement |
| Camille | ajouter à un look le carnet intime (perso), la bague (coffre perso), la guirlande (carton privé), le contrat (Bureau privé), la montre (Collection perso), le manteau (garde-meuble privé) | ❌ 422 ; ces objets ne sont pas proposés à l'écran |
| Camille | publier son brouillon sans photo | ❌ 422 « Ajoute une photo de ta tenue avant de publier. » |
| Camille | dater un look de demain | ❌ 422 ; le sélecteur ne propose pas de date future |
| Camille | remettre un look publié en brouillon, ou retirer sa photo | ❌ 422 (on supprime le look) |
| Camille | supprimer son look publié | ✅ il disparaît du fil et du profil, sa photo envoyée est effacée |
| Sophie | supprimer un look de Camille | ❌ 403 ; aucun bouton à l'écran |
| Camille | ouvrir son profil | ✅ « Mes looks » (2) et « Brouillons · 1 » |
