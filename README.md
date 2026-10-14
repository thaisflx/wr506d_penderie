# WR506D Penderie

Appli pour ranger, retrouver et partager ses objets, organisés en
**logement > pièce > rangement > catégorie > objet**.

## Démarrage

```bash
docker compose up -d

# Clés JWT (non versionnées), base de données et données de test
docker exec penderie_backend php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker exec penderie_backend php bin/console doctrine:migrations:migrate -n
docker exec penderie_backend php bin/console doctrine:fixtures:load -n
```

- Front (Vue) : http://localhost:5173
- API : http://localhost:8000/api (documentation Swagger)

Comptes de test (mot de passe `password`) : `alice@penderie.test` (le plus de contenu),
`leo@penderie.test` (enfant +18 d'alice), `bob@penderie.test`, `admin@penderie.test`.
