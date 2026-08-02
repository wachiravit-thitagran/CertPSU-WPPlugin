<?php
/**
 * GitHub release updater tests.
 *
 * @package CertPSU\Connector\Tests\Unit\Support
 */

declare(strict_types=1);

namespace CertPSU\Connector\Tests\Unit\Support;

use CertPSU\Connector\Support\Github_Updater;
use CertPSU\TutorLMS\Support\Github_Updater as TutorLMS_Github_Updater;
use PHPUnit\Framework\TestCase;

/**
 * Covers version comparison, release asset selection and the release repository
 * the shipped plugins point at.
 */
final class GithubUpdaterTest extends TestCase {

	/**
	 * Repository used by the fixtures below, never the shipped one.
	 */
	private const REPO = 'owner/repo';

	/**
	 * Reset the stub state the updater reads from.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['mock_transients'] = array();
		$GLOBALS['mock_filters']    = array();
		unset( $GLOBALS['mock_http_response'] );
	}

	/**
	 * Leave no stub state behind for the rest of the suite.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['mock_transients'] = array();
		unset( $GLOBALS['mock_http_response'] );

		parent::tearDown();
	}

	/**
	 * The repository every install checks for releases. A typo there fails
	 * silently, because the GitHub API answers 404 for a misspelled owner
	 * exactly as it does for a repository with no releases: no update is offered
	 * and nothing is logged. Pin it to the `Plugin URI` header of both plugins so
	 * the two cannot drift apart unnoticed.
	 *
	 * @return void
	 */
	public function test_shipped_repository_matches_the_plugin_uri(): void {
		$constants = array(
			'certpsu-connector' => 'CERTPSU_CONNECTOR_GITHUB_REPO',
			'certpsu-tutorlms'  => 'CERTPSU_TUTORLMS_GITHUB_REPO',
		);

		$repos = array();

		foreach ( $constants as $slug => $constant ) {
			$contents = $this->read( $this->plugin_file( $slug ) );

			preg_match( "/define\(\s*'{$constant}',\s*'([^']*)'/", $contents, $defined );
			$repo = $defined[1] ?? '';

			self::assertMatchesRegularExpression(
				'#^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?/[A-Za-z0-9._-]+$#',
				$repo,
				"{$constant} must be a valid GitHub \"owner/repo\" slug."
			);

			preg_match( '#Plugin URI:\s*https://github\.com/(\S+?)/?\s*$#m', $contents, $uri );

			self::assertSame(
				strtolower( $repo ),
				strtolower( $uri[1] ?? '' ),
				"{$slug}: {$constant} and the Plugin URI header must name the same repository."
			);

			$repos[ $slug ] = $repo;
		}

		self::assertCount(
			1,
			array_unique( $repos ),
			'Both plugins ship from the same release, so both must point at the same repository.'
		);
	}

	/**
	 * Each plugin carries its own copy of the updater so it keeps updating
	 * itself even when its sibling is deactivated. The copies must stay
	 * identical apart from the namespace.
	 *
	 * @return void
	 */
	public function test_both_plugins_ship_the_same_updater(): void {
		$connector = $this->read( $this->updater_file( 'certpsu-connector' ) );
		$tutorlms  = $this->read( $this->updater_file( 'certpsu-tutorlms' ) );

		self::assertSame(
			$connector,
			str_replace( 'CertPSU\\TutorLMS', 'CertPSU\\Connector', $tutorlms ),
			'The two copies of Github_Updater have drifted apart.'
		);
	}

	/**
	 * A newer tag is offered, pointing at the built zip rather than a source
	 * archive.
	 *
	 * @return void
	 */
	public function test_offers_an_update_for_a_newer_release(): void {
		$this->seed_release( self::release( 'v0.2.0', array( 'certpsu-connector-0.2.0.zip' ) ) );

		$transient = $this->check( $this->connector( '0.1.5' ) );
		$offered   = $transient->response['certpsu-connector/certpsu-connector.php'] ?? null;

		self::assertIsObject( $offered );
		self::assertSame( '0.2.0', $offered->new_version );
		self::assertSame( 'https://github.test/dl/certpsu-connector-0.2.0.zip', $offered->package );
		self::assertSame( 'certpsu-connector', $offered->slug );
		self::assertSame( 'certpsu-connector/certpsu-connector.php', $offered->plugin );
		self::assertSame( array(), $transient->no_update );
	}

	/**
	 * Version comparison is numeric, not lexicographic, and tolerates the "v"
	 * prefix GitHub tags carry. An up-to-date plugin is reported as such, so
	 * WordPress stops asking for the rest of the cycle.
	 *
	 * @return void
	 */
	public function test_compares_versions_numerically(): void {
		$cases = array(
			// Release tag, installed version, update expected.
			array( 'v0.1.6', '0.1.5', true ),
			array( '0.2.0', '0.1.5', true ),
			array( 'v0.10.0', '0.9.0', true ),
			array( 'v1.0.0', '0.99.99', true ),
			array( 'v0.1.5', '0.1.5', false ),
			array( 'v0.1.4', '0.1.5', false ),
			array( 'v0.9.0', '0.10.0', false ),
			array( 'v1.0.0', 'v1.0.0', false ),
		);

		$key = 'certpsu-connector/certpsu-connector.php';

		foreach ( $cases as list( $tag, $installed, $expected ) ) {
			$this->seed_release( self::release( $tag, array( 'certpsu-connector-' . ltrim( $tag, 'v' ) . '.zip' ) ) );

			$transient = $this->check( $this->connector( $installed ) );

			if ( $expected ) {
				self::assertArrayHasKey( $key, $transient->response, "{$tag} should update {$installed}." );
				self::assertArrayNotHasKey( $key, $transient->no_update );
				continue;
			}

			self::assertArrayNotHasKey( $key, $transient->response, "{$tag} should not update {$installed}." );
			self::assertArrayHasKey( $key, $transient->no_update, "{$installed} is current and must land in no_update." );
		}
	}

	/**
	 * The release carries one zip per plugin; each updater must pick its own.
	 *
	 * @return void
	 */
	public function test_each_plugin_picks_its_own_versioned_asset(): void {
		$this->seed_release(
			self::release(
				'v1.2.3',
				array( 'certpsu-connector-1.2.3.zip', 'certpsu-tutorlms-1.2.3.zip' )
			)
		);

		$connector = $this->check( $this->connector( '0.1.5' ) );
		$tutorlms  = $this->check( $this->tutorlms( '0.1.5' ) );

		self::assertSame(
			'https://github.test/dl/certpsu-connector-1.2.3.zip',
			$connector->response['certpsu-connector/certpsu-connector.php']->package
		);
		self::assertSame(
			'https://github.test/dl/certpsu-tutorlms-1.2.3.zip',
			$tutorlms->response['certpsu-tutorlms/certpsu-tutorlms.php']->package
		);
	}

	/**
	 * An unversioned asset name is accepted too, so the matcher survives a
	 * change in how bin/build.sh names the zips.
	 *
	 * @return void
	 */
	public function test_accepts_an_unversioned_asset_name(): void {
		$this->seed_release( self::release( 'v1.2.3', array( 'certpsu-connector.zip' ) ) );

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertSame(
			'https://github.test/dl/certpsu-connector.zip',
			$transient->response['certpsu-connector/certpsu-connector.php']->package
		);
	}

	/**
	 * Assets that merely start with the slug, or that sit next to the zip, are
	 * not the plugin.
	 *
	 * @return void
	 */
	public function test_ignores_assets_that_only_look_like_this_plugin(): void {
		$this->seed_release(
			self::release(
				'v1.2.3',
				array(
					'certpsu-connector-pro-1.2.3.zip',
					'certpsu-connector-1.2.3.zip.sha256',
					'certpsu-connector-1.2.3.tar.gz',
					'certpsu-tutorlms-1.2.3.zip',
				)
			)
		);

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertArrayNotHasKey( 'certpsu-connector/certpsu-connector.php', $transient->response );
	}

	/**
	 * The source zipball of a monorepo unpacks to the repository root, not to a
	 * plugin, so a release without a built asset must offer nothing at all.
	 *
	 * @return void
	 */
	public function test_never_falls_back_to_the_source_zipball(): void {
		$this->seed_release( self::release( 'v9.9.9', array() ) );

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertSame( array(), $transient->response );
	}

	/**
	 * The asset matcher is overridable for releases named some other way.
	 *
	 * @return void
	 */
	public function test_asset_pattern_can_be_overridden(): void {
		$this->seed_release( self::release( 'v1.2.3', array( 'connector-build-1.2.3.zip' ) ) );

		$updater = new Github_Updater(
			$this->plugin_file( 'certpsu-connector' ),
			'certpsu-connector',
			self::REPO,
			'0.1.5',
			'#^connector-build-.*\.zip$#'
		);

		self::assertSame(
			'https://github.test/dl/connector-build-1.2.3.zip',
			$this->check( $updater )->response['certpsu-connector/certpsu-connector.php']->package
		);
	}

	/**
	 * WordPress passes an empty value before the first update check.
	 *
	 * @return void
	 */
	public function test_leaves_a_transient_that_is_not_an_object_alone(): void {
		self::assertFalse( $this->connector( '0.1.5' )->inject_update( false ) );
	}

	/**
	 * A 404 (a missing repository, or one without releases) must offer nothing,
	 * raise nothing, and be cached so the Plugins screen stops asking.
	 *
	 * @return void
	 */
	public function test_a_404_offers_nothing_and_is_cached(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 404 ),
			'body'     => '{"message":"Not Found"}',
		);

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertSame( array(), $transient->response );
		self::assertSame( array(), $transient->no_update );
		self::assertSame( array(), $GLOBALS['mock_transients'][ self::cache_key() ] ?? null );
	}

	/**
	 * A transport failure behaves the same way.
	 *
	 * @return void
	 */
	public function test_a_failed_request_offers_nothing_and_is_cached(): void {
		$GLOBALS['mock_http_response'] = new \WP_Error( 'http_request_failed', 'Connection timed out' );

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertSame( array(), $transient->response );
		self::assertSame( array(), $GLOBALS['mock_transients'][ self::cache_key() ] ?? null );
	}

	/**
	 * The cached empty array a failure leaves behind is "no release", not a
	 * malformed one.
	 *
	 * @return void
	 */
	public function test_reads_a_cached_failure_as_no_release(): void {
		$this->seed_release( array() );

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertSame( array(), $transient->response );
		self::assertSame( array(), $transient->no_update );
	}

	/**
	 * A successful lookup is cached, so both plugins share one API call.
	 *
	 * @return void
	 */
	public function test_caches_a_successful_lookup(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( self::release( 'v0.2.0', array( 'certpsu-connector-0.2.0.zip' ) ) ),
		);

		$transient = $this->check( $this->connector( '0.1.5' ) );

		self::assertSame( '0.2.0', $transient->response['certpsu-connector/certpsu-connector.php']->new_version );
		self::assertSame( 'v0.2.0', $GLOBALS['mock_transients'][ self::cache_key() ]['tag_name'] ?? null );
	}

	/**
	 * A response body that is not a release is not cached as one.
	 *
	 * @return void
	 */
	public function test_a_malformed_response_offers_nothing(): void {
		$GLOBALS['mock_http_response'] = array(
			'response' => array( 'code' => 200 ),
			'body'     => 'not json',
		);

		self::assertSame( array(), $this->check( $this->connector( '0.1.5' ) )->response );
	}

	/**
	 * The details modal describes the release for this plugin.
	 *
	 * @return void
	 */
	public function test_plugin_info_describes_the_release(): void {
		$this->seed_release( self::release( 'v1.2.3', array( 'certpsu-connector-1.2.3.zip' ) ) );

		$info = $this->connector( '0.1.5' )->plugin_info(
			false,
			'plugin_information',
			(object) array( 'slug' => 'certpsu-connector' )
		);

		self::assertIsObject( $info );
		self::assertSame( 'certpsu-connector', $info->slug );
		self::assertSame( '1.2.3', $info->version );
		self::assertSame( 'https://github.test/dl/certpsu-connector-1.2.3.zip', $info->download_link );
		self::assertSame( 'https://github.com/' . self::REPO, $info->homepage );
		self::assertSame( 'CertPSU Connector', $info->name );
		self::assertSame( '8.2', $info->requires_php );
		self::assertStringContainsString( 'Something changed', $info->sections['changelog'] );
	}

	/**
	 * Without a reachable release it still describes the installed plugin.
	 *
	 * @return void
	 */
	public function test_plugin_info_falls_back_to_the_installed_version(): void {
		$this->seed_release( array() );

		$info = $this->connector( '0.1.5' )->plugin_info(
			false,
			'plugin_information',
			(object) array( 'slug' => 'certpsu-connector' )
		);

		self::assertIsObject( $info );
		self::assertSame( '0.1.5', $info->version );
		self::assertSame( '', $info->download_link );
		self::assertSame( '', $info->sections['changelog'] );
	}

	/**
	 * Requests for other plugins, or other actions, pass straight through.
	 *
	 * @return void
	 */
	public function test_plugin_info_ignores_anything_else(): void {
		$updater = $this->connector( '0.1.5' );

		self::assertFalse( $updater->plugin_info( false, 'plugin_information', (object) array( 'slug' => 'certpsu-tutorlms' ) ) );
		self::assertFalse( $updater->plugin_info( false, 'query_plugins', (object) array( 'slug' => 'certpsu-connector' ) ) );
		self::assertFalse( $updater->plugin_info( false, 'plugin_information', null ) );
	}

	/**
	 * The extracted folder is left alone unless it belongs to this plugin.
	 *
	 * @return void
	 */
	public function test_fix_source_dir_only_touches_this_plugin(): void {
		$updater = $this->connector( '0.1.5' );

		self::assertSame(
			'/tmp/upgrade/whatever/',
			$updater->fix_source_dir( '/tmp/upgrade/whatever/', '/tmp/upgrade/', null, array( 'plugin' => 'other/other.php' ) )
		);
		self::assertSame(
			'/tmp/upgrade/certpsu-connector/',
			$updater->fix_source_dir(
				'/tmp/upgrade/certpsu-connector/',
				'/tmp/upgrade/',
				null,
				array( 'plugin' => 'certpsu-connector/certpsu-connector.php' )
			)
		);
		self::assertSame( '/tmp/upgrade/x/', $updater->fix_source_dir( '/tmp/upgrade/x/', '/tmp/upgrade/', null, 'nonsense' ) );
	}

	/**
	 * A repository that cannot be queried is not worth hooking at all.
	 *
	 * @return void
	 */
	public function test_register_skips_an_unusable_repository(): void {
		foreach ( array( '', '   ', 'no-slash', '/' ) as $repo ) {
			$GLOBALS['mock_filters'] = array();

			( new Github_Updater( $this->plugin_file( 'certpsu-connector' ), 'certpsu-connector', $repo, '0.1.5' ) )->register();

			self::assertSame( array(), $GLOBALS['mock_filters'], "Repository '{$repo}' should not be hooked." );
		}

		$GLOBALS['mock_filters'] = array();
		$this->connector( '0.1.5' )->register();

		self::assertArrayHasKey( 'pre_set_site_transient_update_plugins', $GLOBALS['mock_filters'] );
		self::assertArrayHasKey( 'plugins_api', $GLOBALS['mock_filters'] );
		self::assertArrayHasKey( 'upgrader_source_selection', $GLOBALS['mock_filters'] );
	}

	/**
	 * An updater for the connector.
	 *
	 * @param string $version Installed version.
	 * @return Github_Updater
	 */
	private function connector( string $version ): Github_Updater {
		return new Github_Updater(
			$this->plugin_file( 'certpsu-connector' ),
			'certpsu-connector',
			self::REPO,
			$version
		);
	}

	/**
	 * An updater for the TutorLMS bridge.
	 *
	 * @param string $version Installed version.
	 * @return TutorLMS_Github_Updater
	 */
	private function tutorlms( string $version ): TutorLMS_Github_Updater {
		return new TutorLMS_Github_Updater(
			$this->plugin_file( 'certpsu-tutorlms' ),
			'certpsu-tutorlms',
			self::REPO,
			$version
		);
	}

	/**
	 * Run one update check and hand back the resulting transient.
	 *
	 * @param object $updater Updater under test.
	 * @return object
	 */
	private function check( object $updater ): object {
		return $updater->inject_update(
			(object) array(
				'response'  => array(),
				'no_update' => array(),
			)
		);
	}

	/**
	 * Put a release into the cache the updater reads.
	 *
	 * @param array<string,mixed> $release Release payload, or array() for the
	 *                                     empty entry a failed call leaves.
	 * @return void
	 */
	private function seed_release( array $release ): void {
		set_transient( self::cache_key(), $release );
	}

	/**
	 * Transient key the fixtures live under.
	 *
	 * @return string
	 */
	private static function cache_key(): string {
		return 'certpsu_gh_release_' . md5( self::REPO );
	}

	/**
	 * A release payload shaped like the one the release workflow publishes.
	 *
	 * @param string        $tag         Release tag.
	 * @param array<string> $asset_names Attached asset filenames.
	 * @return array<string,mixed>
	 */
	private static function release( string $tag, array $asset_names ): array {
		$assets = array();

		foreach ( $asset_names as $name ) {
			$assets[] = array(
				'name'                 => $name,
				'browser_download_url' => 'https://github.test/dl/' . $name,
			);
		}

		return array(
			'tag_name'     => $tag,
			'body'         => "## What's Changed\n\n* Something changed.",
			'published_at' => '2026-08-01T00:00:00Z',
			'assets'       => $assets,
			'zipball_url'  => 'https://github.test/zipball/' . $tag,
		);
	}

	/**
	 * Read a file from the repository.
	 *
	 * @param string $path Absolute path.
	 * @return string
	 */
	private function read( string $path ): string {
		// A local source file, not a remote resource.
		return (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Absolute path to a plugin's main file.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private function plugin_file( string $slug ): string {
		return dirname( __DIR__, 3 ) . "/plugins/{$slug}/{$slug}.php";
	}

	/**
	 * Absolute path to a plugin's copy of the updater.
	 *
	 * @param string $slug Plugin slug.
	 * @return string
	 */
	private function updater_file( string $slug ): string {
		return dirname( __DIR__, 3 ) . "/plugins/{$slug}/includes/Support/Github_Updater.php";
	}
}
