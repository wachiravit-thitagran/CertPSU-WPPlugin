#!/usr/bin/env bash
#
# Build distributable WordPress plugin zips from the monorepo.
#
# Each directory under plugins/ becomes its own installable zip, so the pieces
# can be shipped and installed independently (e.g. just the core connector, or
# core + a TutorLMS integration, or future integrations).
#
#   dist/certpsu-connector-<version>.zip   (core)
#   dist/certpsu-tutorlms-<version>.zip    (TutorLMS integration)
#   dist/<future-integration>-<version>.zip
#
# Usage:
#   bin/build.sh                  # build every package under plugins/
#   bin/build.sh certpsu-connector
#   bin/build.sh --version 1.2.3  # override the version stamped on the zip name
#   bin/build.sh --output /tmp/out
#
set -euo pipefail

# --- locate repo root ------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGINS_DIR="${ROOT_DIR}/plugins"
# Staging and output dirs can be redirected via env (handy for CI or sandboxes
# where the working tree is read-only): BUILD_STAGING_DIR / BUILD_OUTPUT_DIR.
STAGING_DIR="${BUILD_STAGING_DIR:-${ROOT_DIR}/build/staging}"
OUTPUT_DIR="${BUILD_OUTPUT_DIR:-${ROOT_DIR}/dist}"

# Packages that must bundle Action Scheduler so they work as a standalone zip.
BUNDLE_ACTION_SCHEDULER=("certpsu-connector")
ACTION_SCHEDULER_SRC="${ROOT_DIR}/vendor/woocommerce/action-scheduler"

# Files/dirs never shipped inside a package zip.
EXCLUDES=(
	".git" ".github" ".gitignore" ".DS_Store" "node_modules"
	"tests" ".phpunit.cache" "phpunit.xml" "phpunit.xml.dist"
	"phpstan.neon" "phpstan.neon.dist" "phpcs.xml" "phpcs.xml.dist"
	"composer.lock" "*.map"
)

# --- parse args ------------------------------------------------------------
VERSION_OVERRIDE=""
REQUESTED_PACKAGES=()
while [[ $# -gt 0 ]]; do
	case "$1" in
		--version) VERSION_OVERRIDE="${2:-}"; shift 2 ;;
		--output)  OUTPUT_DIR="${2:-}"; shift 2 ;;
		-h|--help)
			grep '^#' "$0" | sed 's/^#//'; exit 0 ;;
		--*) echo "Unknown option: $1" >&2; exit 1 ;;
		*) REQUESTED_PACKAGES+=("$1"); shift ;;
	esac
done

command -v zip >/dev/null 2>&1 || { echo "ERROR: 'zip' is required but not installed." >&2; exit 1; }

# --- helpers ---------------------------------------------------------------
in_array() { local n="$1"; shift; for e in "$@"; do [[ "$e" == "$n" ]] && return 0; done; return 1; }

read_version() {
	# Extract "Version: x.y.z" from a plugin header file.
	local file="$1"
	grep -iE '^\s*\*?\s*Version:' "$file" 2>/dev/null \
		| head -n1 \
		| sed -E 's/.*[Vv]ersion:[[:space:]]*([0-9A-Za-z._-]+).*/\1/'
}

prune_excludes() {
	# Remove excluded files/dirs from a staged tree.
	local dir="$1" pat
	for pat in "${EXCLUDES[@]}"; do
		find "$dir" -depth -name "$pat" -exec rm -rf {} + 2>/dev/null || true
	done
}

bundle_action_scheduler() {
	local dest="$1/vendor/woocommerce/action-scheduler"
	if [[ ! -d "$ACTION_SCHEDULER_SRC" ]]; then
		echo "  ! Action Scheduler not found at vendor/. Running 'composer install --no-dev'..."
		( cd "$ROOT_DIR" && composer install --no-dev --no-interaction --quiet ) \
			|| { echo "ERROR: could not obtain Action Scheduler. Run 'composer install' first." >&2; exit 1; }
	fi
	mkdir -p "$dest"
	cp -R "$ACTION_SCHEDULER_SRC/." "$dest/"
	# Slim the library: drop its own VCS/test/doc cruft.
	for pat in ".git" "tests" "docs" ".github"; do
		find "$dest" -depth -name "$pat" -exec rm -rf {} + 2>/dev/null || true
	done
	echo "  + bundled Action Scheduler"
}

build_package() {
	local slug="$1"
	local src="${PLUGINS_DIR}/${slug}"
	local main="${src}/${slug}.php"

	[[ -d "$src" ]] || { echo "SKIP: ${slug} (no such directory)"; return; }

	local version="$VERSION_OVERRIDE"
	if [[ -z "$version" && -f "$main" ]]; then
		version="$(read_version "$main")"
	fi
	[[ -z "$version" ]] && version="0.0.0"

	echo "==> Building ${slug} (v${version})"

	local stage="${STAGING_DIR}/${slug}"
	rm -rf "$stage"
	mkdir -p "$stage"
	cp -R "${src}/." "$stage/"
	prune_excludes "$stage"

	if in_array "$slug" "${BUNDLE_ACTION_SCHEDULER[@]}"; then
		bundle_action_scheduler "$stage"
	fi

	mkdir -p "$OUTPUT_DIR"
	local zipfile="${OUTPUT_DIR}/${slug}-${version}.zip"
	rm -f "$zipfile"
	# Zip with the slug as the top-level folder so WordPress installs it cleanly.
	( cd "$STAGING_DIR" && zip -rq "$zipfile" "$slug" -x '*.DS_Store' )
	echo "  -> ${zipfile} ($(du -h "$zipfile" | cut -f1))"
}

# --- discover packages -----------------------------------------------------
discover_packages() {
	for d in "${PLUGINS_DIR}"/*/; do
		[[ -d "$d" ]] && basename "$d"
	done
}

main() {
	rm -rf "$STAGING_DIR"
	mkdir -p "$STAGING_DIR"

	local packages=()
	if [[ ${#REQUESTED_PACKAGES[@]} -gt 0 ]]; then
		packages=("${REQUESTED_PACKAGES[@]}")
	else
		mapfile -t packages < <(discover_packages)
	fi

	[[ ${#packages[@]} -eq 0 ]] && { echo "No packages found under plugins/."; exit 1; }

	for slug in "${packages[@]}"; do
		build_package "$slug"
	done

	echo ""
	echo "Done. Artifacts in: ${OUTPUT_DIR}/"
	ls -1 "$OUTPUT_DIR"/*.zip 2>/dev/null | sed 's#.*/#  #' || true
}

main
