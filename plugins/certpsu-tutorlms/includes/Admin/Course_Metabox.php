<?php
/**
 * Per-course CertPSU settings metabox.
 *
 * @package CertPSU\TutorLMS
 */

declare(strict_types=1);

namespace CertPSU\TutorLMS\Admin;

use CertPSU\TutorLMS\Issuance\Course_Class_Manager;
use CertPSU\TutorLMS\Settings\Course_Settings;
use CertPSU\TutorLMS\Settings\Settings_Sanitizer;

/**
 * Adds and persists the certificate settings metabox on the course editor.
 */
final class Course_Metabox {

	private const NONCE_ACTION = 'certpsu_course_settings_save';
	private const NONCE_NAME   = 'certpsu_course_settings_nonce';
	private const INPUT_PREFIX = 'certpsu_course';

	/**
	 * Constructor.
	 *
	 * @param Settings_Sanitizer $sanitizer Sanitizer.
	 */
	public function __construct( private Settings_Sanitizer $sanitizer = new Settings_Sanitizer() ) {}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post_' . Course_Settings::COURSE_POST_TYPE, array( $this, 'save' ), 10, 1 );
	}

	/**
	 * Register the metabox.
	 *
	 * @return void
	 */
	public function add_metabox(): void {
		add_meta_box(
			'certpsu-course-settings',
			__( 'CertPSU Certificate', 'certpsu-tutorlms' ),
			array( $this, 'render' ),
			Course_Settings::COURSE_POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the metabox.
	 *
	 * @param \WP_Post $post Post.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$class_id = ( new Course_Class_Manager() )->class_id( (int) $post->ID );
		if ( '' !== $class_id ) {
			echo '<p class="certpsu-class-id"><strong>' . esc_html__( 'CertPSU class id:', 'certpsu-tutorlms' ) . '</strong> <code>' . esc_html( $class_id ) . '</code></p>';
		} else {
			echo '<p class="description">' . esc_html__( 'A CertPSU class is created automatically the first time a learner completes this course.', 'certpsu-tutorlms' ) . '</p>';
		}

		// Retroactive sync link.
		$sync_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=certpsu_tutorlms_sync_retroactive&course_id=' . $post->ID ),
			'certpsu_tutorlms_sync_retroactive'
		);
		echo '<p><a href="' . esc_url( $sync_url ) . '" class="button button-secondary">' . esc_html__( 'Sync past completions', 'certpsu-tutorlms' ) . '</a></p>';

		$values   = Course_Settings::for_course( (int) $post->ID );
		$renderer = new Field_Renderer( self::INPUT_PREFIX );
		$renderer->render_all( $values );
	}

	/**
	 * Save the metabox.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw = isset( $_POST[ self::INPUT_PREFIX ] ) && is_array( $_POST[ self::INPUT_PREFIX ] )
			? wp_unslash( $_POST[ self::INPUT_PREFIX ] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field-by-field below.
			: array();

		$clean = $this->sanitizer->sanitize( $raw );
		update_post_meta( $post_id, Course_Settings::META_KEY, $clean );
	}
}
