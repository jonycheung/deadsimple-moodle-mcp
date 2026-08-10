# Development entry points for local_simplemcp.
#
# `make up` builds a throwaway Moodle site with the plugin already installed.
# Everything else assumes that site is running. `make help` lists the lot.

SHELL := /bin/bash
COMPOSE := docker compose
MOODLE := $(COMPOSE) exec -T moodle
MOODLE_WEB := $(COMPOSE) exec -T -u www-data moodle
PLUGIN_DIR := local-simplemcp
WEB_PORT ?= 8080

.DEFAULT_GOAL := help

# ---------------------------------------------------------------------------
# Environment
# ---------------------------------------------------------------------------

.PHONY: up
up: ## Start the stack and install Moodle + the plugin (first run takes a few minutes)
	$(COMPOSE) up -d --build
	@echo "Waiting for Moodle to finish installing..."
	@until $(COMPOSE) logs moodle 2>/dev/null | grep -q "Ready →"; do \
		if [ "$$($(COMPOSE) ps -q moodle)" = "" ]; then echo "moodle container exited"; exit 1; fi; \
		sleep 5; \
	done
	@$(COMPOSE) logs moodle | grep -E "Site installed|Ready →|MCP endpoint" || true

.PHONY: down
down: ## Stop the stack, keeping the database and Moodle checkout
	$(COMPOSE) down

.PHONY: clean
clean: ## Stop the stack and delete every volume (full reset)
	$(COMPOSE) down -v --remove-orphans

.PHONY: logs
logs: ## Follow the Moodle container log
	$(COMPOSE) logs -f moodle

.PHONY: shell
shell: ## Open a shell in the Moodle container
	$(COMPOSE) exec moodle bash

.PHONY: psql
psql: ## Open a psql prompt on the dev database
	$(COMPOSE) exec db psql -U moodle -d moodle

# ---------------------------------------------------------------------------
# Test data and manual checks
# ---------------------------------------------------------------------------

.PHONY: seed
seed: ## Create a test course, learner and bearer token
	$(MOODLE_WEB) php /opt/dev/bin/seed.php

.PHONY: reseed
reseed: ## Delete and recreate the seeded course
	$(MOODLE_WEB) php /opt/dev/bin/seed.php --reset

.PHONY: smoke
smoke: ## Drive every MCP tool over real HTTP against the dev site
	$(MOODLE) env MCP_URL=http://localhost/local/simplemcp/endpoint.php bash /opt/dev/bin/smoke.sh

.PHONY: oauth-smoke
oauth-smoke: ## Drive the whole OAuth flow: consent, PKCE, rotation, revocation
	$(MOODLE) env SITE=http://localhost bash /opt/dev/bin/oauth-smoke.sh

.PHONY: token
token: ## Print the seeded learner's bearer token
	@$(MOODLE) cat /var/www/moodledata/simplemcp-dev-token; echo

# ---------------------------------------------------------------------------
# Automated tests and checks — the same ones CI runs
# ---------------------------------------------------------------------------

.PHONY: phpunit-init
phpunit-init: ## Initialise the PHPUnit test database (needed once per reset)
	$(MOODLE_WEB) php admin/tool/phpunit/cli/init.php

.PHONY: phpunit
phpunit: ## Run this plugin's PHPUnit suite
	$(MOODLE_WEB) vendor/bin/phpunit --testsuite local_simplemcp_testsuite --no-coverage

.PHONY: phpunit-filter
phpunit-filter: ## Run one test, e.g. make phpunit-filter FILTER=test_ping
	$(MOODLE_WEB) vendor/bin/phpunit --testsuite local_simplemcp_testsuite --no-coverage --filter '$(FILTER)'

.PHONY: phpcs
phpcs: ## Check the Moodle coding standard
	$(MOODLE) moodle-plugin-ci phpcs --max-warnings 0 /var/www/html/local/simplemcp

.PHONY: phpcbf
phpcbf: ## Auto-fix what the coding standard can fix
	$(MOODLE) /opt/moodle-plugin-ci/vendor/bin/phpcbf \
		--standard=moodle /var/www/html/local/simplemcp || true

.PHONY: phpdoc
phpdoc: ## Check PHPDoc completeness
	$(MOODLE) moodle-plugin-ci phpdoc --max-warnings 0 /var/www/html/local/simplemcp

.PHONY: phpmd
phpmd: ## Run the mess detector
	$(MOODLE) moodle-plugin-ci phpmd /var/www/html/local/simplemcp

.PHONY: mustache
mustache: ## Lint the Mustache templates
	$(MOODLE) moodle-plugin-ci mustache /var/www/html/local/simplemcp

.PHONY: validate
validate: ## Check version.php, lang strings and plugin structure
	$(MOODLE) moodle-plugin-ci validate /var/www/html/local/simplemcp

.PHONY: check
check: phpcs phpdoc mustache validate phpunit ## Run every check CI runs

.PHONY: behat
behat: ## Run the Behat features (starts a Chrome container)
	$(COMPOSE) --profile behat up -d selenium
	$(MOODLE_WEB) php admin/tool/behat/cli/init.php
	$(MOODLE_WEB) vendor/bin/behat \
		--config /var/www/behat_moodledata/behatrun/behat/behat.yml \
		--profile chrome --tags @local_simplemcp

# ---------------------------------------------------------------------------
# Release
# ---------------------------------------------------------------------------

.PHONY: version
version: ## Print the plugin metadata a release would publish
	@php dev/bin/plugin-meta.php $(PLUGIN_DIR)/version.php

.PHONY: zip
zip: ## Build the installable zip locally, exactly as the release workflow does
	@rm -rf build && mkdir -p build/simplemcp
	@cp -a $(PLUGIN_DIR)/. build/simplemcp/
	@cp LICENSE CHANGELOG.md build/simplemcp/
	@cd build && zip -r -q -X ../local_simplemcp.zip simplemcp
	@rm -rf build
	@echo "Built local_simplemcp.zip"
	@unzip -l local_simplemcp.zip | tail -3

.PHONY: help
help: ## List these targets
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'
