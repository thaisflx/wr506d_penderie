PHP  = docker exec -w /var/www/backend penderie_backend
NODE = docker exec -w /app penderie_frontend

.PHONY: help phpstan lint audit quality fixtures-dev fixtures-test test-db test test-unit test-integration test-functional coverage

help: ## Affiche les commandes disponibles
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

phpstan: ## Analyse statique PHPStan (niveau 6)
	$(PHP) php bin/console cache:warmup
	$(PHP) vendor/bin/phpstan analyse --memory-limit=1G

lint: ## Linters Symfony (conteneur, YAML, Twig, composer.json)
	$(PHP) php bin/console lint:container
	$(PHP) php bin/console lint:yaml config --parse-tags
	$(PHP) php bin/console lint:twig templates
	$(PHP) composer validate

audit: ## Failles de sécurité des dépendances (Composer + npm)
	$(PHP) composer audit
	$(NODE) npm audit

quality: lint phpstan audit ## Lance tous les contrôles qualité

fixtures-dev: ## Recharge les fixtures du groupe dev dans la base de dev
	$(PHP) php bin/console doctrine:fixtures:load --group=dev -n

fixtures-test: ## Recharge les fixtures du groupe test dans la base de test (penderie_db_test)
	$(PHP) php bin/console doctrine:fixtures:load --group=test -n --env=test

test-db: ## (Re)crée la base de test : création, migrations, fixtures du groupe test
	$(PHP) php bin/console doctrine:database:drop --if-exists --force --env=test
	$(PHP) php bin/console doctrine:database:create --env=test
	$(PHP) php bin/console doctrine:migrations:migrate -n --env=test
	$(MAKE) fixtures-test

test: ## Lance tous les tests PHPUnit (unit, integration, functional)
	$(PHP) php bin/phpunit

test-unit: ## Tests unitaires (sans base de données)
	$(PHP) php bin/phpunit --testsuite unit

test-integration: ## Tests d'intégration (base de test)
	$(PHP) php bin/phpunit --testsuite integration

test-functional: ## Tests fonctionnels de l'API (base de test)
	$(PHP) php bin/phpunit --testsuite functional

coverage: ## Couverture du code métier (texte + rapport HTML dans backend/var/coverage)
	$(PHP) php bin/phpunit --coverage-text --coverage-html var/coverage
