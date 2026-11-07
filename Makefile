.PHONY: build test verify up down run shell logs migrate seed security lint format help

COMPOSE_PROJECT_NAME ?= php-docker-p5
COMPOSE_FILE ?= docker-compose.yaml
DEV_COMPOSE_FILE ?= docker-compose.dev.yaml
APP_DIR ?= app

# Port block reserved in projects/info/state/ports.md: 20140-20149
# Map host 20140->80 (web), 20141->3306 (db), 20142->6379 (cache) when needed.

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-22s\033[0m %s\n", $$1, $$2}'

build: ## Build all images (base->deps->build->test->runtime)
	docker build -t php-docker:app -f php/Dockerfile --target app .
	docker build -t php-docker:web -f nginx/Dockerfile .

test: ## Run the test suite inside the Docker test stage
	docker build -t php-docker:test -f php/Dockerfile --target test .
	docker run --rm php-docker:test

verify: build test ## Build and test (full quality gate)

up: verify ## Start the stack (only after verify passes)
	docker compose -f $(COMPOSE_FILE) --env-file=.env up -d

down: ## Stop and remove the stack
	docker compose -f $(COMPOSE_FILE) down

run: ## Start dev overlay (bind-mounts app/, builds locally)
	docker compose -f $(COMPOSE_FILE) -f $(DEV_COMPOSE_FILE) --env-file=.env.example up -d

shell: ## Open a shell in the app container
	docker compose -f $(COMPOSE_FILE) run --rm app sh

logs: ## Show container logs
	docker compose -f $(COMPOSE_FILE) logs -f

migrate: ## Seed the database (run once on first start)
	docker compose -f $(COMPOSE_FILE) exec -T db mysql -u"$$MYSQL_USER" -p"$$MYSQL_PASSWORD" "$$MYSQL_DATABASE" < sql/docker-php.sql

seed: migrate ## Alias for migrate

lint: ## Lint PHP files (placeholder — no linter configured yet)
	@echo "No linter configured; add PHP-CS-Fixer/PHPStan in Phase 9"

format: ## Format PHP files (placeholder)
	@echo "No formatter configured; add PHP-CS-Fixer in Phase 9"

security: ## Run dependency audit (placeholder — needs composer in Docker)
	docker run --rm -v "$$(pwd)/$(APP_DIR):/app" -w /app composer:2.10 composer audit --no-interaction || true
