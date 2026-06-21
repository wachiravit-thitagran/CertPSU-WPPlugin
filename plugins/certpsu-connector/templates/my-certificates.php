<?php
/**
 * My Certificates Template.
 *
 * This template can be overridden by copying it to yourtheme/certpsu/my-certificates.php.
 *
 * @package CertPSU\Connector\Templates
 *
 * @var array<int,array<string,mixed>> $certificates List of certificates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $certificates ) ) {
	echo '<div class="certpsu-my-certificates-empty"><p>' . esc_html__( 'You have not received any certificates yet.', 'certpsu-connector' ) . '</p></div>';
	return;
}
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
			<?php foreach ( $certificates as $cert ) : ?>
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
