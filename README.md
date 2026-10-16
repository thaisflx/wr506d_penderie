# WR506D Penderie

Appli pour ranger, retrouver et partager ses objets, organisés en
**logement > pièce > rangement > catégorie > objet**.

## Démarrage

```bash
docker compose up -d

# Clés JWT (non versionnées), base de données et données de test
docker exec penderie_backend php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker exec penderie_backend php bin/console doctrine:migrations:migrate -n
docker exec penderie_backend php bin/console doctrine:fixtures:load --group=dev -n
```

- Front (Vue) : http://localhost:5173
- API : http://localhost:8000/api (documentation Swagger)

Comptes de test (mot de passe `password`) : `alice@penderie.test` (le plus de contenu),
`leo@penderie.test` (enfant +18 d'alice), `bob@penderie.test`, `admin@penderie.test`.

## Tests

- `backend/tests/` : tests PHPUnit, en trois dossiers `Unit/`, `Integration/` et `Functional/`
- `frontend/cypress/e2e/` : parcours e2e Cypress
- `backend/src/DataFixtures/Test/` : données du groupe `test`, décrites dans [docs/campagne-test.md](docs/campagne-test.md)

```bash
make test-db    # une fois : base de test penderie_db_test
make test       # 155 tests PHPUnit
make coverage   # couverture du code métier (100 %)
make e2e        # 13 tests Cypress, sur la machine hôte
```

- [coverage.md](coverage.md) : périmètre, cas testés, résultats et limites
- [ci.md](ci.md) : toutes les commandes, les captures et l'installation sur une machine neuve
