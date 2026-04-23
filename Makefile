.PHONY: default shell ssh artisan phpstan deploy-build deploy-check-ssh-env deploy-check-ssh-public-env deploy-public-prepare deploy-ssh deploy-ssh-delete deploy-ssh-public deploy-ssh-public-delete deploy-ssh-all deploy-all

COMPOSE_FILE := /Users/junedelacruz/Desktop/aaa-project/docker/compose.yaml
SERVICE := photobooth-crm-php
DEPLOY_WORK_DIR := .deploy
DEPLOY_PUBLIC_DIR := $(DEPLOY_WORK_DIR)/public_html

-include .env.deploy
export

default: shell

shell:
	docker compose -f $(COMPOSE_FILE) exec $(SERVICE) sh

ssh: shell

artisan:
	docker compose -f $(COMPOSE_FILE) exec $(SERVICE) php artisan $(filter-out $@,$(MAKECMDGOALS))

phpstan:
	./vendor/bin/phpstan analyse --memory-limit=2G

deploy-build:
	composer install --no-dev --optimize-autoloader
	npm install
	npm run build
	php artisan optimize:clear

deploy-check-ssh-env:
	@test -n "$(SSH_HOST)" || (echo "Missing SSH_HOST in .env.deploy" && exit 1)
	@test -n "$(SSH_USER)" || (echo "Missing SSH_USER in .env.deploy" && exit 1)
	@test -n "$(SSH_REMOTE_APP_DIR)" || (echo "Missing SSH_REMOTE_APP_DIR in .env.deploy" && exit 1)
	@command -v rsync >/dev/null || (echo "Missing rsync." && exit 1)
	@command -v ssh >/dev/null || (echo "Missing ssh." && exit 1)

deploy-check-ssh-public-env: deploy-check-ssh-env
	@test -n "$(SSH_REMOTE_PUBLIC_DIR)" || (echo "Missing SSH_REMOTE_PUBLIC_DIR in .env.deploy" && exit 1)
	@test -n "$(DEPLOY_SERVER_APP_PATH)" || (echo "Missing DEPLOY_SERVER_APP_PATH in .env.deploy" && exit 1)

deploy-public-prepare:
	rm -rf "$(DEPLOY_PUBLIC_DIR)"
	mkdir -p "$(DEPLOY_PUBLIC_DIR)"
	rsync -a --exclude="storage/" --exclude="hot" "$(or $(DEPLOY_LOCAL_DIR),$(CURDIR))/public/" "$(DEPLOY_PUBLIC_DIR)/"
	sed "s#__APP_PATH__#$(DEPLOY_SERVER_APP_PATH)#g" deploy/hostinger-shared/index.php.stub > "$(DEPLOY_PUBLIC_DIR)/index.php"

deploy-ssh: deploy-check-ssh-env
	ssh -p $(or $(SSH_PORT),22) $(SSH_USER)@$(SSH_HOST) "mkdir -p $(SSH_REMOTE_APP_DIR)"
	rsync -avz \
		-e "ssh -p $(or $(SSH_PORT),22)" \
		--exclude=".git/" \
		--exclude=".idea/" \
		--exclude=".deploy/" \
		--exclude="node_modules/" \
		--exclude=".env" \
		--exclude=".env.deploy" \
		--exclude=".env.prod" \
		--exclude=".phpunit.result.cache" \
		--exclude="public/hot" \
		--exclude="storage/logs/" \
		--exclude="storage/framework/cache/" \
		--exclude="storage/framework/sessions/" \
		--exclude="storage/framework/views/" \
		"$(or $(DEPLOY_LOCAL_DIR),$(CURDIR))/" \
		$(SSH_USER)@$(SSH_HOST):$(SSH_REMOTE_APP_DIR)/

deploy-ssh-delete: deploy-check-ssh-env
	ssh -p $(or $(SSH_PORT),22) $(SSH_USER)@$(SSH_HOST) "mkdir -p $(SSH_REMOTE_APP_DIR)"
	rsync -avz --delete \
		-e "ssh -p $(or $(SSH_PORT),22)" \
		--exclude=".git/" \
		--exclude=".idea/" \
		--exclude=".deploy/" \
		--exclude="node_modules/" \
		--exclude=".env" \
		--exclude=".env.deploy" \
		--exclude=".env.prod" \
		--exclude=".phpunit.result.cache" \
		--exclude="public/hot" \
		--exclude="storage/logs/" \
		--exclude="storage/framework/cache/" \
		--exclude="storage/framework/sessions/" \
		--exclude="storage/framework/views/" \
		"$(or $(DEPLOY_LOCAL_DIR),$(CURDIR))/" \
		$(SSH_USER)@$(SSH_HOST):$(SSH_REMOTE_APP_DIR)/

deploy-ssh-public: deploy-check-ssh-public-env deploy-public-prepare
	ssh -p $(or $(SSH_PORT),22) $(SSH_USER)@$(SSH_HOST) "mkdir -p $(SSH_REMOTE_PUBLIC_DIR)"
	rsync -avz \
		-e "ssh -p $(or $(SSH_PORT),22)" \
		--exclude="storage/" \
		--exclude="hot" \
		"$(DEPLOY_PUBLIC_DIR)/" \
		$(SSH_USER)@$(SSH_HOST):$(SSH_REMOTE_PUBLIC_DIR)/

deploy-ssh-public-delete: deploy-check-ssh-public-env deploy-public-prepare
	ssh -p $(or $(SSH_PORT),22) $(SSH_USER)@$(SSH_HOST) "mkdir -p $(SSH_REMOTE_PUBLIC_DIR)"
	rsync -avz --delete \
		-e "ssh -p $(or $(SSH_PORT),22)" \
		--exclude="storage/" \
		--exclude="hot" \
		"$(DEPLOY_PUBLIC_DIR)/" \
		$(SSH_USER)@$(SSH_HOST):$(SSH_REMOTE_PUBLIC_DIR)/

deploy-ssh-all: deploy-ssh deploy-ssh-public

deploy-all: deploy-build deploy-ssh-all

%:
	@:
