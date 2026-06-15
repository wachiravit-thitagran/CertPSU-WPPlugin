.PHONY: build build-core build-tutorlms clean check test phpcs phpstan

# Build every installable plugin zip into dist/.
build:
	bin/build.sh

# Build a single package.
build-core:
	bin/build.sh certpsu-connector

build-tutorlms:
	bin/build.sh certpsu-tutorlms

# Remove build artifacts.
clean:
	rm -rf build dist

# Dev tooling (delegates to composer scripts).
check:
	composer check

test:
	composer test

phpcs:
	composer phpcs

phpstan:
	composer phpstan
