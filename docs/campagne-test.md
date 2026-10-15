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
| `marc@test.local` | **Bloqué** → aucun droit |
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

- **Looks** : « Look du dimanche » publié (contient le carnet perso, qui doit rester masqué) ; « Brouillon soirée » non publié
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
| Marc (bloqué) | voir quoi que ce soit | ❌ |
| N'importe qui sauf Camille | voir le Coffre à bijoux, la Collection, le Bureau | ❌ |
| Zoé (random) | ouvrir le lien de la sélection | ✅ aperçu seulement |
| Zoé | ouvrir le lien expiré ou révoqué | ❌ |
| `suspendu@test.local` | se connecter | ❌ « Ce compte est suspendu. » |
| Camille | voir l'adresse de la Maison | ✅ (personne d'autre) |
