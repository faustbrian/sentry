compose_command = docker-compose run -u $(id -u ${USER}):$(id -g ${USER}) --rm php84

build:
	docker-compose build

shell: build
	$(compose_command) bash

destroy:
	docker-compose down -v

composer: build
	$(compose_command) composer install

lint: build
	$(compose_command) composer lint

refactor: build
	$(compose_command) composer refactor

test: build
	$(compose_command) composer test

test\:lint: build
	$(compose_command) composer test:lint

test\:refactor: build
	$(compose_command) composer test:refactor

test\:type-coverage: build
	$(compose_command) composer test:type-coverage

test\:types: build
	$(compose_command) composer test:types

test\:unit: build
	$(compose_command) composer test:unit

test\:docker: test\:docker\:sqlite test\:docker\:mysql test\:docker\:postgres

test\:docker\:sqlite: build
	docker-compose run --rm -e DB_CONNECTION=sqlite php84 vendor/bin/pest

test\:docker\:mysql: build
	docker-compose run --rm -e DB_CONNECTION=mysql -e DB_HOST=mysql -e DB_PORT=3306 -e DB_DATABASE=warden_test -e DB_USERNAME=root -e DB_PASSWORD=password php84 vendor/bin/pest

test\:docker\:postgres: build
	docker-compose run --rm -e DB_CONNECTION=pgsql -e DB_HOST=postgres -e DB_PORT=5432 -e DB_DATABASE=warden_test -e DB_USERNAME=postgres -e DB_PASSWORD=password php84 vendor/bin/pest
