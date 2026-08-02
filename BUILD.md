# Building the plugins

This repository is a monorepo that ships as **several independent WordPress
plugin zips**, so each piece can be installed on its own:

| Package | Slug | Zip | Depends on |
|---|---|---|---|
| Core connector | `certpsu-connector` | `certpsu-connector-<version>.zip` | — (bundles Action Scheduler) |
| TutorLMS integration | `certpsu-tutorlms` | `certpsu-tutorlms-<version>.zip` | core connector (active) |

You can install just the core (e.g. to drive issuance from your own code via
`certpsu()->create_issuance(...)` or `certpsu()->api()`), or add an integration
zip on top. Future integrations follow the same pattern — one folder, one zip.

## Prerequisites

- PHP 8.2+ and [Composer](https://getcomposer.org/)
- `zip` on the `PATH`

Install dependencies once (provides Action Scheduler + dev tooling):

```bash
composer install
```

## Build

```bash
make build              # build every package into dist/
# or
bin/build.sh            # same thing
```

Build a single package, or override the stamped version / output dir:

```bash
bin/build.sh certpsu-connector
bin/build.sh --version 1.2.3
bin/build.sh --output /tmp/release
make build-core         # convenience target
make build-tutorlms
```

Artifacts land in `dist/`:

```
dist/certpsu-connector-0.1.0.zip
dist/certpsu-tutorlms-0.1.0.zip
```

Each zip contains a single top-level folder named after the slug, so it installs
directly through **Plugins → Add New → Upload Plugin** in WordPress.

`bin/build.sh` honours `BUILD_STAGING_DIR` and `BUILD_OUTPUT_DIR` environment
variables, useful in CI or when the working tree is read-only.

## What the build does

For every directory under `plugins/`:

1. Stages a clean copy, stripping dev-only files (`tests/`, `phpunit.*`,
   `phpstan.*`, `phpcs.*`, `.git*`, `node_modules/`, `composer.lock`, …).
2. Reads the version from the plugin's `Version:` header for the zip name.
3. For packages listed in `BUNDLE_ACTION_SCHEDULER` (the core connector),
   bundles Action Scheduler into the plugin's own
   `vendor/woocommerce/action-scheduler/` so the zip is self-contained. At
   runtime the connector prefers this bundled copy and falls back to the
   monorepo-root `vendor/` during local development.
4. Zips the staged folder into `dist/<slug>-<version>.zip`.

## Releasing (CI)

`.github/workflows/release.yml` builds the zips and publishes a GitHub Release
**automatically on every push to `main`** — no manual tagging needed. The release
version comes from the core plugin's `Version:` header
(`plugins/certpsu-connector/certpsu-connector.php`):

- Bump the `Version:` header and push → a new release `v<version>` is created at
  that commit, with the zips attached and auto-generated notes.
- Push again without bumping → the existing `v<version>` release's assets are
  refreshed (`--clobber`).

It can also be run manually from the **Actions** tab (workflow_dispatch), which
just uploads the zips as workflow artifacts.

## Updating an installed site

Both plugins update themselves from those releases: each one checks the
repository for a newer tag and installs its own `<slug>-<version>.zip` asset, so
**Dashboard → Updates** offers them like any plugin from wordpress.org. No
wp-admin upload needed after the first install.

The source zipball is deliberately never used as a fallback. This is a monorepo,
so the zipball unpacks to the repository root rather than to a plugin; a release
whose assets are missing simply offers no update.

Each plugin carries its own copy of `includes/Support/Github_Updater.php` so it
stays updatable on its own — the TutorLMS bridge keeps working through an
update even while the connector is deactivated. A test pins the two copies
together, and pins the repository to each plugin's `Plugin URI` header: a typo
in the owner makes the GitHub API answer 404 exactly as it would for a
repository with no releases, so updates would silently stop being offered.

To point an install at a fork, define the constant before the plugin loads or
filter it:

```php
define( 'CERTPSU_CONNECTOR_GITHUB_REPO', 'my-org/CertPSU-WPPlugin' );
define( 'CERTPSU_TUTORLMS_GITHUB_REPO', 'my-org/CertPSU-WPPlugin' );

// Or, for both at once:
add_filter( 'certpsu_github_repo', fn( $repo, $slug ) => 'my-org/CertPSU-WPPlugin', 10, 2 );

// Private fork, or just a higher API rate limit:
add_filter(
	'certpsu_github_request_args',
	function ( array $args ): array {
		$args['headers']['Authorization'] = 'Bearer ' . MY_GITHUB_TOKEN;
		return $args;
	}
);
```

Release lookups are cached in a transient for six hours (one hour after a failed
call), and shared by both plugins.

## Adding a new integration

1. Create `plugins/certpsu-<name>/` with a `certpsu-<name>.php` plugin header
   (mirror `certpsu-tutorlms` as a starting point).
2. `bin/build.sh` auto-discovers the folder and produces
   `dist/certpsu-<name>-<version>.zip` on the next build.
3. If the new package needs Action Scheduler bundled, add its slug to the
   `BUNDLE_ACTION_SCHEDULER` array in `bin/build.sh`. (Integrations that rely on
   the active core connector do **not** need this — Action Scheduler comes from
   core.)
