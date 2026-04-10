# News Aggregator — Makefile
# Usage: make <target>

DOCKER_COMP = docker compose $(if $(wildcard .env.local),--env-file .env.local)
PHP_CONT   = $(DOCKER_COMP) exec php
PHP        = $(PHP_CONT) php
CONSOLE    = $(PHP) bin/console
COMPOSER   = $(PHP_CONT) composer
BUN        = $(PHP_CONT) bun

.DEFAULT_GOAL := help

## —— Docker 🐳 ——————————————————————————————————
.PHONY: build up down start restart logs sh worker-logs

build: ## Build Docker images
	$(DOCKER_COMP) build --pull --no-cache

up: ## Start containers in detached mode
	$(DOCKER_COMP) up -d --wait

down: ## Stop and remove containers
	$(DOCKER_COMP) down --remove-orphans

start: build up ## Build and start containers

restart: down up ## Restart containers

logs: ## Follow container logs
	$(DOCKER_COMP) logs -f

sh: ## Open a shell in the PHP container
	$(PHP_CONT) bash

worker-logs: ## Follow Messenger worker logs
	$(DOCKER_COMP) logs -f worker

ember: ## Open Ember monitoring dashboard (runs inside PHP container where Caddy runs)
	$(PHP_CONT) ember

## —— Composer 🧙 ——————————————————————————————————
.PHONY: composer vendor

composer: ## Run composer (pass arguments via c="...")
	$(COMPOSER) $(c)

vendor: ## Install composer dependencies
	$(COMPOSER) install --prefer-dist --no-progress

## —— Symfony 🎵 ——————————————————————————————————
.PHONY: sf cc sf-migrate

sf: ## Run Symfony console command (pass via c="...")
	$(CONSOLE) $(c)

cc: ## Clear Symfony cache
	$(CONSOLE) cache:clear

sf-migrate: ## Run Doctrine migrations
	$(CONSOLE) doctrine:migrations:migrate --no-interaction --all-or-nothing

## —— Code Quality 🔍 ——————————————————————————————
.PHONY: quality phpstan ecs ecs-fix rector rector-fix

quality: ecs phpstan rector ## Run all quality checks (ECS + PHPStan + Rector)

phpstan: ## Run PHPStan static analysis
	$(PHP_CONT) vendor/bin/phpstan analyse --memory-limit=512M

ecs: ## Run ECS coding standards check
	$(PHP_CONT) vendor/bin/ecs check

ecs-fix: ## Fix ECS coding standards issues
	$(PHP_CONT) vendor/bin/ecs check --fix

rector: ## Run Rector dry-run
	$(PHP_CONT) vendor/bin/rector process --dry-run

rector-fix: ## Apply Rector fixes
	$(PHP_CONT) vendor/bin/rector process

## —— Testing 🧪 ——————————————————————————————————
.PHONY: test test-unit test-integration infection coverage

test: ## Run all PHPUnit tests
	$(PHP_CONT) vendor/bin/phpunit $(c)

test-unit: ## Run unit tests only
	$(PHP_CONT) vendor/bin/phpunit --testsuite=unit

test-integration: ## Run integration tests only
	$(PHP_CONT) vendor/bin/phpunit --testsuite=integration

infection: ## Run Infection mutation testing (unit suite only)
	$(PHP_CONT) vendor/bin/infection --threads=4 --test-framework-options="--testsuite=unit"

coverage: ## Generate code coverage report
	$(PHP_CONT) php -d xdebug.mode=coverage vendor/bin/phpunit --coverage-html var/coverage

## —— TypeScript 📜 ————————————————————————————————
.PHONY: ts-build ts-watch

ts-build: ## Compile TypeScript via Bun
	$(BUN) build assets/ts/*.ts --outdir=assets/js/ --root=assets/ts

ts-watch: ## Watch and compile TypeScript via Bun
	$(BUN) build assets/ts/*.ts --outdir=assets/js/ --root=assets/ts --watch

## —— Database 💾 ——————————————————————————————————
.PHONY: db-create db-drop db-reset export-postgres import-postgres

db-create: ## Create database
	$(CONSOLE) doctrine:database:create --if-not-exists

db-drop: ## Drop database
	$(CONSOLE) doctrine:database:drop --force --if-exists

db-reset: db-drop db-create sf-migrate ## Drop, create, and migrate database

export-postgres: ## Export PostgreSQL backup
	@mkdir -p backup
	$(DOCKER_COMP) exec database pg_dump -U app app > backup/postgres_backup.sql
	@echo "Backup saved to backup/postgres_backup.sql"

import-postgres: ## Import PostgreSQL backup
	$(DOCKER_COMP) exec -T database psql -U app app < backup/postgres_backup.sql
	@echo "Backup restored from backup/postgres_backup.sql"

## —— Git Hooks 🪝 —————————————————————————————————
.PHONY: hooks

hooks: ## Install git hooks
	git config core.hooksPath .githooks
	@echo "Git hooks installed from .githooks/"

## —— Help ❓ ——————————————————————————————————————
.PHONY: help

help: ## Show this help
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' Makefile | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-20s\033[0m %s\n", $$1, $$2}' | sed -e 's/^## /\n/'
