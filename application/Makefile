# ─────────────────────────────────────────────────────────────
#  Command surface. Run `make help` to see everything.
#  Everything runs locally on your machine.
# ─────────────────────────────────────────────────────────────
.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

init: ## Set up THIS folder as an independent app (unique name + free port)
	./bin/init-app.sh

clone: ## Copy public source to a new independent repository: make clone DEST=../../my-new-app
	@test -n "$(DEST)" || { echo "usage: make clone DEST=../../my-new-app"; exit 1; }
	python3 bin/package.py --clone "$(DEST)"

up: ## Start the stack (URL/port from .env, default :8080)
	./bin/start.sh --no-open

down: ## Stop the stack; preserve all data
	./bin/stop.sh

logs: ## Tail logs
	docker compose logs -f

migrate: ## Apply pending schema migrations
	docker compose exec worker php bin/migrate.php

publish: ## Make your latest changes live in the running app (rebuild + migrate)
	./bin/start.sh --no-open --rebuild
	@docker compose exec -T worker php bin/migrate.php

## ── Production (run ON the server; many apps per server) ─────
PROD := docker compose -f docker-compose.yml -f docker-compose.prod.yml
EDGE := docker compose -p edge --project-directory edge -f edge/docker-compose.yml

net: ## Create the shared 'web' network (safe to re-run)
	@docker network inspect web >/dev/null 2>&1 || docker network create web

edge-up: net ## Start the shared auto-SSL proxy (ONCE per server)
	@if [ -n "$$(docker volume ls --filter label=com.docker.compose.project=edge -q)" ]; then echo 'Warning: the existing edge uses Docker volumes. Migrate and verify its certificates before switching to local storage.'; exit 1; fi
	@test -f edge/.env || cp edge/.env.example edge/.env
	$(EDGE) up -d

edge-logs: ## Tail the edge proxy logs (watch SSL issuance here)
	$(EDGE) logs -f

edge-down: ## Stop the shared edge proxy
	$(EDGE) down

prod-up: net ## Start THIS app in production (behind the edge proxy)
	@python3 bin/init-app.py
	@mkdir -p data/cli data/recovery data/audit
	$(PROD) build
	$(PROD) run --rm --build --no-deps prepare
	$(PROD) run --rm --no-deps --user 0:0 --entrypoint python3 cli -c 'import os; os.chown("/state", int(os.environ["APP_UID"]), int(os.environ["APP_GID"])); os.chmod("/state", 0o700)'
	@python3 bin/check-storage.py
	@scheme=""; grep -qiE '^SSL=(off|false|no|0)[[:space:]]*$$' .env 2>/dev/null && scheme="http://"; \
	[ -n "$$scheme" ] && echo "→ SSL is OFF: serving plain HTTP for $${DOMAIN:-the domain}"; \
	CADDY_SCHEME="$$scheme" $(PROD) up -d --build --wait
	$(PROD) exec -T postgres sh /docker-entrypoint-initdb.d/10-site.sh
	$(PROD) exec -T worker php bin/migrate.php

prod-migrate: ## Apply migrations on the production stack
	$(PROD) exec -T worker php bin/migrate.php

prod-logs: ## Tail this app's production logs
	$(PROD) logs -f

prod-down: ## Stop this app's production stack
	$(PROD) down

deploy: ## Push code to the live server + migrate (set DEPLOY_* in .env)
	./bin/deploy.sh

uptime: ## Check the live site is up; alert if down (set UPTIME_URL in .env)
	./bin/uptime-check.sh

shell: ## Open a shell in the php container
	docker compose exec php sh

## ── Backups (data safety) ───────────────────────────────────
backup: ## Back up the database, admin state and site files to private ./backups
	./bin/backup.sh

backups: ## List saved private recovery folders
	@ls -ld backups/*/ 2>/dev/null || echo "No backups yet. Run: make backup"

restore: ## Restore the database: make restore FILE=backups/<folder>/database.dump.enc
	@test -n "$(FILE)" || { echo "usage: make restore FILE=backups/<folder>/database.dump.enc"; exit 1; }
	./bin/restore.sh "$(FILE)"

psql: ## Open psql on the database
	docker compose exec postgres psql -U $${POSTGRES_USER:-app} -d $${POSTGRES_DB:-app}

reset: ## Reset the app to a clean default state (wipes the database — asks first)
	./bin/reset.sh

.PHONY: help init clone up down logs migrate publish shell psql backup backups restore deploy uptime reset net edge-up edge-logs edge-down prod-up prod-migrate prod-logs prod-down
