# ============================================================================
# Makefile for InvoicePlane (CI3 + MX)
# Unified Host & Docker Development Workflow
# ============================================================================

COMPOSE ?= docker compose
APP_SERVICE ?= app

PHPUNIT ?= vendor/bin/phpunit
PLAYWRIGHT ?= npx playwright

.PHONY: help install test test-unit test-feature test-filter test-fail e2e e2e-list \
	dup ddown ddown-volumes drebuild dsh dphpunit dtest dtest-filter dtest-fail de2e

install:
	composer install
	yarn install

test: test-unit test-feature

test-unit:
	$(PHPUNIT) --testsuite Unit

test-feature:
	$(PHPUNIT) --testsuite Feature

test-filter:
	$(PHPUNIT) --filter $(f)

test-fail:
	$(PHPUNIT) --stop-on-failure

e2e:
	$(PLAYWRIGHT) test

e2e-list:
	$(PLAYWRIGHT) test --list

# --- Docker helpers ---

dup:
	$(COMPOSE) up -d

ddown:
	$(COMPOSE) down

ddown-volumes:
	$(COMPOSE) down -v

drebuild:
	$(COMPOSE) down -v && $(COMPOSE) build --no-cache && $(COMPOSE) up -d

dsh:
	$(COMPOSE) exec $(APP_SERVICE) bash

dphpunit: dtest

dtest:
	$(COMPOSE) exec $(APP_SERVICE) $(PHPUNIT)

dtest-filter:
	$(COMPOSE) exec $(APP_SERVICE) $(PHPUNIT) --filter $(f)

dtest-fail:
	$(COMPOSE) exec $(APP_SERVICE) $(PHPUNIT) --stop-on-failure

de2e:
	$(COMPOSE) exec $(APP_SERVICE) $(PLAYWRIGHT) test

help:
	@echo "================================ InvoicePlane Make targets ================================"
	@echo "Host:"
	@echo "  make install            Install composer + yarn dependencies"
	@echo "  make test               Run Unit + Feature phpunit suites"
	@echo "  make test-unit          Run Unit suite"
	@echo "  make test-feature       Run Feature suite"
	@echo "  make test-filter f=...  Run filtered PHPUnit tests"
	@echo "  make e2e                Run Playwright tests"
	@echo ""
	@echo "Docker:"
	@echo "  make dup / ddown          Start/stop docker compose (no volume removal)"
	@echo "  make ddown-volumes        Stop and remove volumes"
	@echo "  make drebuild             Rebuild containers"
	@echo "  make dsh                  Shell into app container"
	@echo "  make dtest / dphpunit     Run phpunit in app container (dphpunit is an alias for dtest)"
	@echo "  make de2e                 Run Playwright in app container"
	@echo "==========================================================================================="

.DEFAULT_GOAL := help
