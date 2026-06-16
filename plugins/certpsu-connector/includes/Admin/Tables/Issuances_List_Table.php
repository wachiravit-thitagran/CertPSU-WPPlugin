<?php
/**
 * Issuances List Table.
 *
 * @package CertPSU\Connector\Admin\Tables
 */

declare(strict_types=1);

namespace CertPSU\Connector\Admin\Tables;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Issuances table.
 */
class Issuances_List_Table extends \WP_List_Table {

	/**
	 * Get columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns(): array {
		return array(
			'id'                 => 'ID',
			'certpsu_class_name' => 'CertPSU Class Name',
			'participant_email'  => 'Participant Email',
			'created_at'         => 'Created At',
			'status'             => 'Status',
			'updated_at'         => 'Updated At',
			'tools'              => 'Tools',
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		global $wpdb;

		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		$table_issuances = $wpdb->prefix . 'certpsu_issuances';
		$table_certs     = $wpdb->prefix . 'certpsu_certificates';

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table_issuances}" );

		$sql = $wpdb->prepare(
			"SELECT i.*, 
				(SELECT c.email FROM {$table_certs} c WHERE c.issuance_id = i.id ORDER BY c.id ASC LIMIT 1) as participant_email,
				(SELECT c.certificate_url FROM {$table_certs} c WHERE c.issuance_id = i.id ORDER BY c.id ASC LIMIT 1) as certificate_url
			FROM {$table_issuances} i
			ORDER BY i.id DESC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		);

		$this->items = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Render default column.
	 *
	 * @param array<string,mixed> $item        Row data.
	 * @param string              $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ): string {
		switch ( $column_name ) {
			case 'id':
			case 'status':
			case 'created_at':
			case 'updated_at':
			case 'participant_email':
				return esc_html( (string) ( $item[ $column_name ] ?? '' ) );
			default:
				return print_r( $item[ $column_name ] ?? '', true );
		}
	}

	/**
	 * Render CertPSU Class Name column.
	 *
	 * @param array<string,mixed> $item Row data.
	 * @return string
	 */
	public function column_certpsu_class_name( array $item ): string {
		$payload_json = (string) ( $item['class_payload_json'] ?? '{}' );
		$payload      = json_decode( $payload_json, true );
		$class_name   = '';
		if ( is_array( $payload ) ) {
			$class_name = $payload['printed_name'] ?? $payload['class_name'] ?? '';
		}

		if ( '' === $class_name && ! empty( $item['certpsu_class_id'] ) ) {
			$class_name = $item['certpsu_class_id'];
		}

		return esc_html( (string) $class_name );
	}

	/**
	 * Render Tools column.
	 *
	 * @param array<string,mixed> $item Row data.
	 * @return string
	 */
	public function column_tools( array $item ): string {
		$tools       = array();
		$issuance_id = (int) $item['id'];
		$class_id    = (string) ( $item['certpsu_class_id'] ?? '' );
		$status      = (string) ( $item['status'] ?? '' );
		$cert_url    = (string) ( $item['certificate_url'] ?? '' );

		// View on CertPSU.
		if ( '' !== $class_id ) {
			$config_json = (string) ( $item['certpsu_config_json'] ?? '{}' );
			$config      = json_decode( $config_json, true );
			$org_id      = is_array( $config ) && isset( $config['organization_id'] ) ? $config['organization_id'] : '';
			
			$view_url = sprintf( 'https://cert.psu.ac.th/th/admin/classes/%s', urlencode( $class_id ) );
			if ( '' !== $org_id ) {
				$view_url = add_query_arg( 'organization_id', urlencode( (string) $org_id ), $view_url );
			}
			
			$tools[]  = sprintf(
				'<a href="%1$s" target="_blank" title="%2$s"><span class="dashicons dashicons-external"></span> View on CertPSU</a>',
				esc_url( $view_url ),
				esc_attr__( 'View on CertPSU', 'certpsu-connector' )
			);
		}

		// Sync Certificate URL.
		if ( 'released' === $status && '' === $cert_url ) {
			$sync_url = wp_nonce_url(
				add_query_arg(
					array(
						'page'        => 'certpsu-connector-issuances',
						'action'      => 'refetch_urls',
						'issuance_id' => $issuance_id,
					),
					admin_url( 'admin.php' )
				),
				'certpsu_refetch_' . $issuance_id
			);

			$tools[] = sprintf(
				'<a href="%1$s" title="%2$s"><span class="dashicons dashicons-update"></span> Sync Certificate URL</a>',
				esc_url( $sync_url ),
				esc_attr__( 'Sync Certificate URL', 'certpsu-connector' )
			);
		}

		// View Certificate.
		if ( '' !== $cert_url ) {
			$tools[] = sprintf(
				'<a href="%1$s" target="_blank" title="%2$s"><span class="dashicons dashicons-awards"></span> View Certificate</a>',
				esc_url( $cert_url ),
				esc_attr__( 'View Certificate', 'certpsu-connector' )
			);
		}

		return implode( '<br>', $tools );
	}
}
