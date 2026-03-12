.DEFAULT_GOAL := help
DOCKER_COMPOSE_DEV  := docker compose -f docker-compose-dev.yml
DOCKER_COMPOSE_TEST := docker compose -f docker-compose-test.yml
APP_DEV             := $(DOCKER_COMPOSE_DEV) exec app
APP_TEST            := $(DOCKER_COMPOSE_TEST) run --rm app

##@ Project

.PHONY: install
install: ## Build images and install dependencies
	$(DOCKER_COMPOSE_DEV) build
	$(DOCKER_COMPOSE_DEV) up -d
	$(DOCKER_COMPOSE_DEV) exec app composer install

##@ Development

.PHONY: up
up: ## Start dev environment
	$(DOCKER_COMPOSE_DEV) up -d

.PHONY: down
down: ## Stop dev environment
	$(DOCKER_COMPOSE_DEV) down

.PHONY: logs
logs: ## Follow dev logs
	$(DOCKER_COMPOSE_DEV) logs -f

.PHONY: shell
shell: ## Open a shell in the dev app container
	$(APP_DEV) bash

##@ Dependencies

.PHONY: composer-install
composer-install: ## Run composer install in dev container
	$(APP_DEV) composer install

.PHONY: composer-update
composer-update: ## Run composer update in dev container
	$(APP_DEV) composer update

##@ Architecture

.PHONY: deptrac
deptrac: ## Check architecture layer dependencies (Hexagonal Architecture)
	$(APP_DEV) vendor/bin/deptrac analyse --config-file=deptrac.yaml

##@ Tests

.PHONY: test
test: ## Run PHPUnit test suite
	$(DOCKER_COMPOSE_TEST) up -d db
	$(DOCKER_COMPOSE_TEST) exec -T db sh -c 'until pg_isready -U meetsync -d meetsync_test; do sleep 1; done'
	$(APP_TEST) vendor/bin/phpunit
	$(DOCKER_COMPOSE_TEST) down

.PHONY: test-coverage
test-coverage: ## Run PHPUnit with code coverage (pcov)
	$(DOCKER_COMPOSE_TEST) up -d db
	$(DOCKER_COMPOSE_TEST) exec -T db sh -c 'until pg_isready -U meetsync -d meetsync_test; do sleep 1; done'
	$(APP_TEST) vendor/bin/phpunit --coverage-text --coverage-html var/coverage
	$(DOCKER_COMPOSE_TEST) down

.PHONY: test-down
test-down: ## Stop test environment
	$(DOCKER_COMPOSE_TEST) down

##@ Help

.PHONY: help
help: ## Display this help message
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m<target>\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)
