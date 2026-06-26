<?php
/**
 * PHPUnit bootstrap for plugin tests.
 *
 * @package CertPSU\Connector\Tests
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

\DG\BypassFinals::enable();

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

class WPDieException extends \Exception {}

if ( ! function_exists( 'certpsu' ) ) {
	class Mock_CertPSU {
		public function api() {
			return $GLOBALS['mock_certpsu_api'] ?? new class {};
		}
		public function container() {
			return $GLOBALS['mock_certpsu_container'] ?? new class {
				public function get($key) {
					if ($key === 'queue') {
						return new class extends \CertPSU\Connector\Queue\Queue {
							public function __construct() {}
							public function resolve() {}
						};
					}
					if ($key === 'process_issuance_workflow_service') {
						return new class {
							public function handle($id) { return 1; }
						};
					}
					return null;
				}
			};
		}
		public function create_issuance( $payload = null ) {
			if ( $payload && isset( $payload['idempotency_mode'] ) && 'fail_if_exists' === $payload['idempotency_mode'] ) {
				return new \WP_Error( 'certpsu_issuance_exists', 'Exists' );
			}
			return 1;
		}
		public function get_issuance( $id = null ) {
			return array( 'id' => $id, 'class_id' => 'class_123', 'status' => 'released' );
		}
		public function replacer() {
			return new class {
				public function replace($text, $context) { return $text; }
				public function replace_recursive($body, $context) { return $body; }
			};
		}
	}
	function certpsu() {
		return new Mock_CertPSU();
	}
}

class Mock_WPDB {
	public $prefix = 'wp_';
	public $insert_id = 1;
	public $last_query = '';
	public $last_table = '';
	public $last_data = array();
	
	public function insert( $table, $data, $format = null ) {
		$this->last_table = $table;
		$this->last_data = $data;
		return 1;
	}

	public function query( $query ) {
		$this->last_query = $query;
		return 1;
	}
}
$GLOBALS['wpdb'] = new Mock_WPDB();

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return 1;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Provide a minimal plugin_dir_path() stub for unit tests.
	 *
	 * @param string $file Plugin file path.
	 *
	 * @return string
	 */
	function plugin_dir_path( string $file ): string {
		return dirname( $file ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * Provide a minimal plugin_dir_url() stub for unit tests.
	 *
	 * @param string $file Plugin file path.
	 *
	 * @return string
	 */
	function plugin_dir_url( string $file ): string {
		return 'http://example.org/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Minimal sanitize_text_field() stub.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ): string {
		return is_string( $value ) ? trim( (string) preg_replace( '/[\r\n\t ]+/', ' ', $value ) ) : '';
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Minimal sanitize_textarea_field() stub.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	function sanitize_textarea_field( $value ): string {
		return is_string( $value ) ? trim( $value ) : '';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Minimal get_the_title() stub.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	function get_the_title( $id ): string {
		return 'Course ' . (int) $id;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( $url, $args = array() ) {
		if ( isset( $GLOBALS['mock_http_response'] ) ) {
			return $GLOBALS['mock_http_response'];
		}
		return array( 'response' => array( 'code' => 200 ), 'body' => '{}' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public function __construct( private $code = '', private $message = '' ) {}
		public function get_error_code() { return $this->code; }
		public function get_error_message() { return $this->message; }
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return $response['response']['code'] ?? 200;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		if ( isset( $GLOBALS['mock_options'][$option] ) ) {
			return $GLOBALS['mock_options'][$option];
		}
		return $default;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		} elseif ( ! is_array( $args ) ) {
			wp_parse_str( $args, $args );
		}
		if ( is_array( $defaults ) && $defaults ) {
			return array_merge( $defaults, $args );
		}
		return $args;
	}
}

if ( ! function_exists( 'wp_parse_str' ) ) {
	function wp_parse_str( $string, &$array ) {
		parse_str( (string) $string, $array );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return '2023-01-01 00:00:00';
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( isset( $GLOBALS['mock_post_meta'][$post_id][$key] ) ) {
			return $GLOBALS['mock_post_meta'][$post_id][$key];
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return $GLOBALS['mock_current_user_id'] ?? 1;
	}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( $hook, $args = array(), $group = '' ) {
		$GLOBALS['mock_async_actions'][] = array( 'hook' => $hook, 'args' => $args, 'group' => $group );
		return 123;
	}
}

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	function as_schedule_single_action( $timestamp, $hook, $args = array(), $group = '' ) {
		$GLOBALS['mock_scheduled_actions'][] = array( 'timestamp' => $timestamp, 'hook' => $hook, 'args' => $args, 'group' => $group );
		return 124;
	}
}

if ( ! class_exists( 'ActionScheduler' ) ) {
	class ActionScheduler {
		public static function is_initialized() { return true; }
	}
}

if ( ! function_exists( 'get_user_meta' ) ) {
	function get_user_meta( $user_id, $key = '', $single = false ) {
		if ( isset( $GLOBALS['mock_user_meta'][$user_id][$key] ) ) {
			return $GLOBALS['mock_user_meta'][$user_id][$key];
		}
		return $single ? '' : array();
	}
}

if ( ! function_exists( 'update_user_meta' ) ) {
	function update_user_meta( $user_id, $key, $value, $prev_value = '' ) {
		$GLOBALS['mock_user_meta'][$user_id][$key] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_actions'][$tag][] = $callback;
	}
}



if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $func ) {
		$GLOBALS['mock_shortcodes'][$tag] = $func;
	}
}

if ( ! function_exists( 'wp_set_current_user' ) ) {
	function wp_set_current_user( $id ) {
		$GLOBALS['mock_current_user_id'] = $id;
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		return $min;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post = null ) {
		$post_id = is_object($post) ? $post->ID : (is_array($post) ? $post['ID'] : $post);
		if ( ! $post_id ) return null;
		$p = new stdClass();
		$p->ID = $post_id;
		$p->post_title = get_the_title($post_id);
		$p->post_date = current_time('mysql');
		return $p;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['mock_filters'][$tag][] = $callback;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $string ) {
		return rtrim( $string, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'doing_action' ) ) {
	function doing_action( $tag = null ) {
		return false;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $tag ) {
		return ! empty( $GLOBALS['mock_actions'][$tag] ) ? 1 : 0;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return $GLOBALS['mock_post_titles'][$post] ?? 'Mock Title';
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box() {
		$GLOBALS['mock_meta_boxes'][] = func_get_args();
	}
}

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page() {
		$GLOBALS['mock_submenu_pages'][] = func_get_args();
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting() {
		$GLOBALS['mock_registered_settings'][] = func_get_args();
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( $option_group ) {
		echo '<input type="hidden" name="option_page" value="' . esc_attr( $option_group ) . '" />';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button() {
		echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes" />';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field() {}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return $nonce === 'valid_nonce';
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $cap = '' ) {
		if ( $cap === 'manage_options' && ($GLOBALS['mock_current_user_id'] ?? 1) === 2 ) {
			return false;
		}
		return true;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return $str;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value ) {
		$GLOBALS['mock_post_meta'][$post_id][$meta_key] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	function wp_nonce_url( $actionurl, $action = -1, $name = '_wpnonce' ) {
		return $actionurl . '&_wpnonce=valid_nonce';
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return $url;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg() {
		$args = func_get_args();
		if ( is_array( $args[0] ) ) {
			if ( count( $args ) < 2 || false === $args[1] ) {
				$uri = $_SERVER['REQUEST_URI'] ?? '';
			} else {
				$uri = $args[1];
			}
		} else {
			if ( count( $args ) < 3 || false === $args[2] ) {
				$uri = $_SERVER['REQUEST_URI'] ?? '';
			} else {
				$uri = $args[2];
			}
		}
		// Minimal mock
		return $uri . ( strpos( $uri, '?' ) === false ? '?' : '&' ) . http_build_query( is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] ) );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new WPDieException( $message );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return $GLOBALS['mock_is_user_logged_in'] ?? true;
	}
}

