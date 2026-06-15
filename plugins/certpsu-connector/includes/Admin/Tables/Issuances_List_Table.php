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
			'id'                => 'ID',
			'external_source'   => 'External Source',
			'external_id'       => 'External ID',
			'status'            => 'Status',
			'current_step'      => 'Current Step',
			'participant_count' => 'Participants',
			'ready_count'       => 'Ready',
			'failed_count'      => 'Failed',
			'auto_release'      => 'Auto Release',
			'certpsu_class_id'  => 'CertPSU Class ID',
			'created_at'        => 'Created At',
			'updated_at'        => 'Updated At',
		);
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items(): void {
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->items           = array();
	}
}
