<?php
/**
 * My Certificates Shortcode.
 *
 * @package CertPSU\Connector\Frontend
 */

declare(strict_types=1);

namespace CertPSU\Connector\Frontend;

use CertPSU\Connector\Support\Json;

/**
 * Handles the [certpsu_my_certificates] shortcode.
 */
final class My_Certificates_Shortcode {

	/**
	 * Register the shortcode.
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'certpsu_my_certificates', array( $this, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your certificates.', 'certpsu-connector' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$core_certificates = certpsu()->container()->get( 'certificates' )->get_by_wp_user_id( $user_id );

		// Format core certificates.
		$formatted_certs = array();
		foreach ( $core_certificates as $cert ) {
			$class_payload = Json::decode( (string) $cert['class_payload_json'] );
			$formatted_certs[] = array(
				'id'              => $cert['certpsu_certificate_id'],
				'title'           => $class_payload['title'] ?? esc_html__( 'Unknown Certificate', 'certpsu-connector' ),
				'certificate_url' => $cert['certificate_url'],
				'issued_at'       => $cert['ready_at'] ?? $cert['updated_at'],
				'source'          => 'core',
			);
		}

		/**
		 * Filter the list of certificates displayed to the user.
		 * 
		 * Plugins like TutorLMS can hook into this to inject their own certificates.
		 * Structure per item:
		 * [
		 *   'id'              => string,
		 *   'title'           => string,
		 *   'certificate_url' => string,
		 *   'issued_at'       => string (MySQL format Y-m-d H:i:s),
		 *   'source'          => string
		 * ]
		 *
		 * @param array<int,array<string,mixed>> $formatted_certs The formatted certificates.
		 * @param int                            $user_id         The WP User ID.
		 */
		$all_certs = apply_filters( 'certpsu_my_certificates_data', $formatted_certs, $user_id );

		// Sort by issued_at DESC.
		usort( $all_certs, function( $a, $b ) {
			return strtotime( $b['issued_at'] ) <=> strtotime( $a['issued_at'] );
		});

		if ( empty( $all_certs ) ) {
			return '<div class="certpsu-my-certificates-empty"><p>' . esc_html__( 'You have not received any certificates yet.', 'certpsu-connector' ) . '</p></div>';
		}

		ob_start();
		?>
		<div class="certpsu-my-certificates">
			<style>
				.certpsu-table {
					width: 100%;
					border-collapse: collapse;
					margin-top: 1em;
					font-family: inherit;
				}
				.certpsu-table th, .certpsu-table td {
					padding: 12px 16px;
					border-bottom: 1px solid #e2e8f0;
					text-align: left;
				}
				.certpsu-table th {
					background-color: #f8fafc;
					font-weight: 600;
					color: #334155;
				}
				.certpsu-btn-view {
					display: inline-block;
					padding: 6px 12px;
					background-color: #2563eb;
					color: #ffffff !important;
					text-decoration: none;
					border-radius: 4px;
					font-size: 14px;
					font-weight: 500;
					transition: background-color 0.2s;
				}
				.certpsu-btn-view:hover {
					background-color: #1d4ed8;
				}
				.certpsu-cert-title {
					font-weight: 500;
					color: #0f172a;
				}
				.certpsu-cert-date {
					color: #64748b;
					font-size: 0.9em;
				}
			</style>
			<table class="certpsu-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Certificate Title', 'certpsu-connector' ); ?></th>
						<th><?php esc_html_e( 'Date Issued', 'certpsu-connector' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $all_certs as $cert ) : ?>
						<?php 
							$date_formatted = '';
							if ( ! empty( $cert['issued_at'] ) ) {
								$date_formatted = date_i18n( get_option( 'date_format' ), strtotime( $cert['issued_at'] ) );
							}
						?>
						<tr>
							<td>
								<div class="certpsu-cert-title"><?php echo esc_html( $cert['title'] ); ?></div>
							</td>
							<td>
								<div class="certpsu-cert-date"><?php echo esc_html( $date_formatted ); ?></div>
							</td>
							<td style="text-align: right;">
								<?php if ( ! empty( $cert['certificate_url'] ) ) : ?>
									<a href="<?php echo esc_url( $cert['certificate_url'] ); ?>" target="_blank" class="certpsu-btn-view">
										<?php esc_html_e( 'View Certificate', 'certpsu-connector' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
}
