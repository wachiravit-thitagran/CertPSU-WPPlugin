<?php
/**
 * Self-hosted plugin updates from GitHub releases.
 *
 * @package CertPSU\TutorLMS\Support
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Support;

/**
 * Offers WordPress an update whenever the release repository publishes a tag
 * newer than the installed version, and installs the built zip attached to that
 * release.
 *
 * The release workflow attaches one zip per plugin with the version in the
 * filename (`certpsu-connector-1.2.3.zip`, `certpsu-tutorlms-1.2.3.zip`), so
 * assets are matched by pattern rather than by an exact name.
 *
 * The source zipball is deliberately never used as a fallback: this is a
 * monorepo, so the zipball's single top-level directory is the repository root
 * (bin/, plugins/, tests/ ...) and installing it would replace the plugin with
 * the whole tree. A release without a matching asset therefore offers no update.
 *
 * One instance per plugin, registered while that plugin boots.
 */
final class Github_Updater {

	/**
	 * Transient key prefix. Shared by every CertPSU plugin, so a single API call
	 * serves them all: they ship from the same release.
	 */
	private const CACHE_PREFIX = 'certpsu_gh_release_';

	/**
	 * GitHub "owner/repo" publishing the releases.
	 *
	 * @var string
	 */
	private string $repo;

	/**
	 * PCRE matched against the release asset names.
	 *
	 * @var string
	 */
	private string $asset_pattern;

	/**
	 * Constructor.
	 *
	 * @param string $file          Absolute path to the plugin's main file.
	 * @param string $slug          Plugin slug, i.e. its installed folder name.
	 * @param string $repo          GitHub "owner/repo" publishing the releases.
	 * @param string $version       Installed version.
	 * @param string $asset_pattern Optional PCRE for the release asset name.
	 *                              Defaults to "<slug>-<version>.zip", the
	 *                              naming produced by bin/build.sh.
	 */
	public function __construct(
		private string $file,
		private string $slug,
		string $repo,
		private string $version,
		string $asset_pattern = ''
	) {
		$this->repo          = trim( $repo, '/ ' );
		$this->asset_pattern = '' !== $asset_pattern
			? $asset_pattern
			// The version suffix is optional, but when present it has to start
			// with a digit: that is what keeps "<slug>-<version>.zip" from also
			// matching a sibling package such as "<slug>-pro-1.0.0.zip".
			: '#^' . preg_quote( $slug, '#' ) . '(?:-\d[\w.\-]*)?\.zip$#';
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( '' === $this->file || '' === $this->repo || ! str_contains( $this->repo, '/' ) ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
	}

	/**
	 * Advertise an available update through the update_plugins transient.
	 *
	 * @param mixed $transient Update transient. WordPress builds it as a plain
	 *                         stdClass, and passes an empty value before the
	 *                         first check.
	 * @return mixed
	 */
	public function inject_update( mixed $transient ): mixed {
		if ( ! $transient instanceof \stdClass ) {
			return $transient;
		}

		$release = $this->latest_release();
		if ( null === $release ) {
			return $transient;
		}

		$new_version = $this->normalize( (string) $release['tag_name'] );

		if ( '' === $new_version || version_compare( $new_version, $this->normalize( $this->version ), '<=' ) ) {
			// Already current. Record it so WordPress stops asking this cycle.
			if ( is_array( $transient->no_update ?? null ) ) {
				$transient->no_update[ $this->basename() ] = $this->update_item( $new_version, '' );
			}

			return $transient;
		}

		$package = $this->package_url( $release );
		if ( '' === $package ) {
			return $transient;
		}

		if ( ! is_array( $transient->response ?? null ) ) {
			$transient->response = array();
		}
		$transient->response[ $this->basename() ] = $this->update_item( $new_version, $package );

		return $transient;
	}

	/**
	 * Supply the "View details" modal content for this plugin.
	 *
	 * @param mixed  $result Value provided by other handlers.
	 * @param string $action Requested plugins_api action.
	 * @param mixed  $args   Request arguments, carrying a `slug`.
	 * @return mixed
	 */
	public function plugin_info( mixed $result, string $action, mixed $args ): mixed {
		$request = is_object( $args ) ? get_object_vars( $args ) : $args;
		$slug    = is_array( $request ) ? (string) ( $request['slug'] ?? '' ) : '';

		if ( 'plugin_information' !== $action || $slug !== $this->slug ) {
			return $result;
		}

		$release = $this->latest_release();
		$headers = $this->plugin_headers();

		$info = array(
			'name'          => (string) ( $headers['Name'] ?? $this->slug ),
			'slug'          => $this->slug,
			'version'       => null !== $release
				? $this->normalize( (string) $release['tag_name'] )
				: $this->normalize( $this->version ),
			'author'        => (string) ( $headers['Author'] ?? '' ),
			'homepage'      => 'https://github.com/' . $this->repo,
			'download_link' => null !== $release ? $this->package_url( $release ) : '',
			'requires'      => (string) ( $headers['RequiresWP'] ?? '' ),
			'requires_php'  => (string) ( $headers['RequiresPHP'] ?? '' ),
			'sections'      => array(
				'description' => wp_kses_post( (string) ( $headers['Description'] ?? '' ) ),
				'changelog'   => $this->release_notes( $release ),
			),
		);

		if ( null !== $release && ! empty( $release['published_at'] ) ) {
			$info['last_updated'] = (string) $release['published_at'];
		}

		return (object) $info;
	}

	/**
	 * Make sure the extracted folder is named after the plugin slug, so the
	 * update lands on top of the existing installation instead of beside it.
	 *
	 * @param mixed $source        Extracted source directory.
	 * @param mixed $remote_source Directory holding the extracted source.
	 * @param mixed $upgrader      Upgrader instance. Unused.
	 * @param mixed $hook_extra    Arguments describing what is being upgraded.
	 * @return mixed
	 */
	public function fix_source_dir( mixed $source, mixed $remote_source, mixed $upgrader = null, mixed $hook_extra = array() ): mixed {
		$extra = is_array( $hook_extra ) ? $hook_extra : array();

		if ( ! is_string( $source ) || ! is_string( $remote_source ) ) {
			return $source;
		}
		if ( (string) ( $extra['plugin'] ?? '' ) !== $this->basename() ) {
			return $source;
		}

		$desired = trailingslashit( $remote_source ) . $this->slug;
		if ( untrailingslashit( $source ) === $desired ) {
			return $source;
		}

		global $wp_filesystem;
		if ( $wp_filesystem instanceof \WP_Filesystem_Base && $wp_filesystem->move( untrailingslashit( $source ), $desired, true ) ) {
			return trailingslashit( $desired );
		}

		return $source;
	}

	/**
	 * Fetch the repository's latest release, cached in a transient.
	 *
	 * A failed or non-200 response is cached briefly as an empty array and
	 * reported here as "no release": a repository that does not exist (a typo in
	 * the owner, say) answers 404 exactly like one that has no releases yet, so
	 * there is nothing to tell apart and nothing worth surfacing on the Plugins
	 * screen.
	 *
	 * @return array<string,mixed>|null Decoded release, or null when unavailable.
	 */
	private function latest_release(): ?array {
		$cache_key = self::CACHE_PREFIX . md5( $this->repo );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return empty( $cached['tag_name'] ) ? null : $cached;
		}

		/**
		 * Filters the GitHub API request arguments, e.g. to add an
		 * Authorization header for a higher rate limit or a private fork.
		 *
		 * @param array<string,mixed> $args Request arguments.
		 * @param string              $repo GitHub "owner/repo".
		 */
		$args = (array) apply_filters(
			'certpsu_github_request_args',
			array(
				'timeout' => 15,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'CertPSU-Updater',
				),
			),
			$this->repo
		);

		$response = wp_remote_get( 'https://api.github.com/repos/' . $this->repo . '/releases/latest', $args );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, array(), HOUR_IN_SECONDS );
			return null;
		}

		$release = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			set_transient( $cache_key, array(), HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Resolve the download URL for a release: this plugin's built zip.
	 *
	 * @param array<string,mixed> $release Release payload.
	 * @return string Empty string when the release carries no matching asset.
	 */
	private function package_url( array $release ): string {
		$assets = is_array( $release['assets'] ?? null ) ? $release['assets'] : array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$url = (string) ( $asset['browser_download_url'] ?? '' );
			if ( '' !== $url && 1 === preg_match( $this->asset_pattern, (string) ( $asset['name'] ?? '' ) ) ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * Build the update descriptor WordPress expects in the transient.
	 *
	 * @param string $new_version Version being offered.
	 * @param string $package     Download URL, or '' when there is nothing to install.
	 * @return \stdClass
	 */
	private function update_item( string $new_version, string $package ): \stdClass {
		$headers = $this->plugin_headers();

		return (object) array(
			'slug'         => $this->slug,
			'plugin'       => $this->basename(),
			'new_version'  => $new_version,
			'url'          => 'https://github.com/' . $this->repo,
			'package'      => $package,
			'icons'        => array(),
			'banners'      => array(),
			'tested'       => '',
			'requires_php' => (string) ( $headers['RequiresPHP'] ?? '' ),
		);
	}

	/**
	 * Render a release's notes as the changelog section. GitHub generates them
	 * per release, so there is no bundled readme.txt to parse.
	 *
	 * @param array<string,mixed>|null $release Release payload.
	 * @return string
	 */
	private function release_notes( ?array $release ): string {
		$body = null !== $release ? trim( (string) ( $release['body'] ?? '' ) ) : '';

		return '' === $body ? '' : wp_kses_post( wpautop( $body ) );
	}

	/**
	 * Read the installed plugin's file headers.
	 *
	 * WordPress defines get_plugin_data() in wp-admin/includes/plugin.php, which
	 * is not loaded on every request that reaches these filters (cron, for one),
	 * so its absence is treated as "no headers" rather than as an error.
	 *
	 * @return array<string,mixed>
	 */
	private function plugin_headers(): array {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			return array();
		}

		return get_plugin_data( $this->file, false, false );
	}

	/**
	 * The plugin's basename, i.e. "<folder>/<main-file>.php", which is the key
	 * WordPress files every plugin under in the update transient.
	 *
	 * @return string
	 */
	private function basename(): string {
		return plugin_basename( $this->file );
	}

	/**
	 * Normalize a tag or version string: "v1.2.3" becomes "1.2.3".
	 *
	 * @param string $version Tag or version.
	 * @return string
	 */
	private function normalize( string $version ): string {
		return ltrim( $version, 'vV' );
	}
}
