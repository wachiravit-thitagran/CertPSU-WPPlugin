<?php
/**
 * API Response.
 *
 * @package CertPSU\Connector\CertPSU
 */

declare(strict_types=1);

namespace CertPSU\Connector\CertPSU;

/**
 * API Response DTO.
 */
final class Api_Response {

	/**
	 * Constructor.
	 *
	 * @param bool                     $success Success flag.
	 * @param int|null                 $status_code HTTP status.
	 * @param array<string,mixed>|null $data Data array.
	 * @param string|null              $raw_body Raw body.
	 * @param string|null              $error_code Error code.
	 * @param string|null              $error_message Error message.
	 * @param int|null                 $api_log_id DB Log ID.
	 */
	public function __construct(
		public readonly bool $success,
		public readonly ?int $status_code,
		public readonly ?array $data,
		public readonly ?string $raw_body,
		public readonly ?string $error_code,
		public readonly ?string $error_message,
		public readonly ?int $api_log_id
	) {}
}
