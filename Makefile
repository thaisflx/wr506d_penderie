PHP  = docker exec -w /var/www/backend penderie_backend
NODE = docker exec -w /app penderie_frontend

.PHONY: help phpstan lint audit quality

help: ## Affiche les commandes disponibles
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

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
