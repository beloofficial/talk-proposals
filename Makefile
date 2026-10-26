SAIL := ./vendor/bin/sail

.DEFAULT_GOAL := help
.PHONY: help setup up down dev test fresh logs

help: ## Show available commands
	@grep -E '^[a-z]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  make %-7s %s\n", $$1, $$2}'

setup: .env vendor ## First run: start containers, migrate and seed, build the frontend
	$(SAIL) up -d --wait
	@if grep -q '^APP_KEY=$$' .env; then $(SAIL) artisan key:generate; fi
	@$(SAIL) artisan migrate:status > /dev/null 2>&1 || $(SAIL) artisan migrate --seed
	$(SAIL) npm install
	$(SAIL) npm run build

.env:
	cp .env.example .env

vendor:
	docker run --rm -u "$$(id -u):$$(id -g)" -v "$(CURDIR):/var/www/html" -w /var/www/html \
		laravelsail/php84-composer:latest composer install --ignore-platform-reqs

up: ## Start the containers
	$(SAIL) up -d --wait

down: ## Stop the containers (data is kept)
	$(SAIL) down

dev: ## Run Vite with hot reload
	$(SAIL) npm run dev

test: ## Run the test suite
	$(SAIL) test

fresh: ## Reset the database to the demo data
	$(SAIL) artisan migrate:fresh --seed

logs: ## Follow WebSocket and queue logs
	$(SAIL) logs -f reverb queue
