<?php
/**
 * Verification Handler for GF VerifyTX
 *
 * @package GF_VerifyTX
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles insurance verification logic.
 */
class GF_VerifyTX_Verification {

	/**
	 * API client instance.
	 *
	 * @var GF_VerifyTX_API_Client
	 */
	private $api_client;

	/**
	 * Constructor.
	 *
	 * @param GF_VerifyTX_API_Client $api_client API client instance.
	 */
	public function __construct( $api_client ) {
		$this->api_client = $api_client;
	}

	/**
	 * Perform insurance verification.
	 *
	 * @param array $data       Verification data.
	 * @param int   $form_id    Form ID.
	 * @param int   $entry_id   Entry ID (optional).
	 * @return array Verification result.
	 */
	public function verify_insurance( $data, $form_id = 0, $entry_id = 0 ) {
		$start_time = microtime( true );

		$cached_result = $this->get_cached_verification( $data );
		if ( $cached_result !== false ) {
			$this->log_debug( 'Using cached verification result' );
			return $cached_result;
		}

		do_action( 'gf_verifytx_before_verification', $data, $form_id );

		$api_response = $this->api_client->create_and_verify_vob( $data );

		if ( is_wp_error( $api_response ) ) {
			$error_data = array(
				'success'     => false,
				'verified'    => false,
				'status'      => 'error',
				'error'       => $api_response->get_error_message(),
				'error_code'  => $api_response->get_error_code(),
				'verified_at' => current_time( 'mysql' ),
				'duration'    => microtime( true ) - $start_time,
			);

			$this->save_verification_record( $data, $error_data, $form_id, $entry_id );

			do_action( 'gf_verifytx_verification_failed', $data, $form_id, $error_data );

			return $error_data;
		}

		$result = $this->process_verification_response( $api_response );
		$result['duration'] = microtime( true ) - $start_time;

		if ( $result['success'] ) {
			$this->cache_verification( $data, $result );
		}

		$this->save_verification_record( $data, $result, $form_id, $entry_id );

		do_action( 'gf_verifytx_after_verification', $data, $form_id, $result );

		if ( $result['success'] ) {
			do_action( 'gf_verifytx_verification_success', $data, $form_id, $result );
		} else {
			do_action( 'gf_verifytx_verification_failed', $data, $form_id, $result );
		}

		return $result;
	}

	/**
	 * Process API response into standardized format.
	 *
	 * @param array $response API response.
	 * @return array Processed result.
	 */
	private function process_verification_response( $response ) {
		$result = array(
			'success'     => ! empty( $response['verified'] ),
			'verified'    => ! empty( $response['verified'] ),
			'status'      => $response['status'] ?? 'unknown',
			'vob_id'      => $response['vob_id'] ?? '',
			'verified_at' => current_time( 'mysql' ),
			'as_of_date'  => $response['as_of_date'] ?? '',
		);

		if ( isset( $response['payer'] ) ) {
			$result['payer'] = $response['payer'];
		}

		if ( isset( $response['subscriber'] ) ) {
			$result['subscriber'] = $response['subscriber'];
		}

		if ( isset( $response['benefits'] ) ) {
			$result['benefits'] = $response['benefits'];
		}

		if ( isset( $response['plans'] ) ) {
			$result['plans'] = $response['plans'];
		}

		if ( isset( $response['error'] ) ) {
			$result['error'] = $response['error'];
			$result['error_ref'] = $response['error_ref'] ?? '';
		}

		return apply_filters( 'gf_verifytx_process_response', $result, $response );
	}

	/**
	 * Save verification record to database.
	 *
	 * @param array $request_data  Request data.
	 * @param array $response_data Response data.
	 * @param int   $form_id       Form ID.
	 * @param int   $entry_id      Entry ID.
	 * @return int|false Insert ID or false on failure.
	 */
	private function save_verification_record( $request_data, $response_data, $form_id = 0, $entry_id = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'gf_verifytx_verifications';

		$data = array(
			'entry_id'          => $entry_id,
			'form_id'           => $form_id,
			'verification_date' => current_time( 'mysql' ),
			'request_data'      => wp_json_encode( $request_data ),
			'response_data'     => wp_json_encode( $response_data ),
			'status'            => $response_data['status'] ?? 'unknown',
		);

		$result = $wpdb->insert( $table, $data );

		if ( $result === false ) {
			$this->log_error( 'Failed to save verification record: ' . $wpdb->last_error );
			return false;
		}

		$this->cleanup_old_records();

		return $wpdb->insert_id;
	}

	/**
	 * Get cached verification result.
	 *
	 * @param array $data Verification data.
	 * @return array|false Cached result or false.
	 */
	private function get_cached_verification( $data ) {
		if ( ! $this->is_caching_enabled() ) {
			return false;
		}

		$cache_key = $this->generate_cache_key( $data );

		global $wpdb;
		$table = $wpdb->prefix . 'gf_verifytx_cache';

		$cached = $wpdb->get_var( $wpdb->prepare(
			"SELECT cache_data FROM $table WHERE cache_key = %s AND expiration > %s",
			$cache_key,
			current_time( 'mysql' )
		) );

		if ( $cached ) {
			$result = json_decode( $cached, true );
			if ( is_array( $result ) ) {
				$result['from_cache'] = true;
				return $result;
			}
		}

		return false;
	}

	/**
	 * Cache verification result.
	 *
	 * @param array $data   Verification data.
	 * @param array $result Verification result.
	 * @return bool Success status.
	 */
	private function cache_verification( $data, $result ) {
		if ( ! $this->is_caching_enabled() || ! $result['success'] ) {
			return false;
		}

		$cache_key = $this->generate_cache_key( $data );
		$cache_duration = $this->get_cache_duration();

		global $wpdb;
		$table = $wpdb->prefix . 'gf_verifytx_cache';

		$expiration = date( 'Y-m-d H:i:s', time() + $cache_duration );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table WHERE cache_key = %s",
			$cache_key
		) );

		if ( $existing ) {
			$updated = $wpdb->update(
				$table,
				array(
					'cache_data' => wp_json_encode( $result ),
					'expiration' => $expiration,
				),
				array( 'cache_key' => $cache_key )
			);
			return $updated !== false;
		} else {
			$inserted = $wpdb->insert(
				$table,
				array(
					'cache_key'  => $cache_key,
					'cache_data' => wp_json_encode( $result ),
					'expiration' => $expiration,
				)
			);
			return $inserted !== false;
		}
	}

	/**
	 * Generate cache key for verification data.
	 *
	 * @param array $data Verification data.
	 * @return string Cache key.
	 */
	private function generate_cache_key( $data ) {
		$key_data = array(
			'member_id'      => $data['member_id'] ?? '',
			'payer_id'       => $data['payer_id'] ?? '',
			'date_of_birth'  => $data['date_of_birth'] ?? '',
			'first_name'     => strtolower( $data['first_name'] ?? '' ),
			'last_name'      => strtolower( $data['last_name'] ?? '' ),
			'group_number'   => $data['group_number'] ?? '',
		);

		ksort( $key_data );
		return 'vtx_' . md5( serialize( $key_data ) );
	}

	/**
	 * Check if caching is enabled.
	 *
	 * @return bool
	 */
	private function is_caching_enabled() {
		$settings = get_option( 'gravityformsaddon_gf-verifytx_settings', array() );
		$duration = isset( $settings['cache_duration'] ) ? intval( $settings['cache_duration'] ) : 24;
		return $duration > 0;
	}

	/**
	 * Get cache duration in seconds.
	 *
	 * @return int Duration in seconds.
	 */
	private function get_cache_duration() {
		$settings = get_option( 'gravityformsaddon_gf-verifytx_settings', array() );
		$hours = isset( $settings['cache_duration'] ) ? intval( $settings['cache_duration'] ) : 24;
		return $hours * 3600;
	}

	/**
	 * Clean up old verification records.
	 */
	private function cleanup_old_records() {
		$settings = get_option( 'gravityformsaddon_gf-verifytx_settings', array() );
		$retention_days = isset( $settings['data_retention'] ) ? intval( $settings['data_retention'] ) : 90;

		if ( $retention_days <= 0 ) {
			return;
		}

		global $wpdb;

		$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}gf_verifytx_verifications WHERE created_at < %s",
			$cutoff_date
		) );

		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}gf_verifytx_cache WHERE expiration < %s",
			current_time( 'mysql' )
		) );
	}

	/**
	 * Get verification history for an entry.
	 *
	 * @param int $entry_id Entry ID.
	 * @return array Verification history.
	 */
	public function get_entry_verification_history( $entry_id ) {
		global $wpdb;

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}gf_verifytx_verifications
			WHERE entry_id = %d
			ORDER BY verification_date DESC",
			$entry_id
		), ARRAY_A );

		foreach ( $results as &$result ) {
			$result['request_data'] = json_decode( $result['request_data'], true );
			$result['response_data'] = json_decode( $result['response_data'], true );
		}

		return $results;
	}

	/**
	 * Get verification statistics.
	 *
	 * @param int    $form_id Form ID (optional).
	 * @param string $period  Time period (today, week, month, all).
	 * @return array Statistics.
	 */
	public function get_verification_stats( $form_id = 0, $period = 'all' ) {
		global $wpdb;

		$where = array();
		$values = array();

		if ( $form_id > 0 ) {
			$where[] = 'form_id = %d';
			$values[] = $form_id;
		}

		switch ( $period ) {
			case 'today':
				$where[] = 'DATE(verification_date) = CURDATE()';
				break;
			case 'week':
				$where[] = 'verification_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)';
				break;
			case 'month':
				$where[] = 'verification_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)';
				break;
		}

		$where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		$query = "SELECT
			COUNT(*) as total,
			SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active,
			SUM(CASE WHEN status = 'Inactive' THEN 1 ELSE 0 END) as inactive,
			SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
			FROM {$wpdb->prefix}gf_verifytx_verifications
			$where_clause";

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare( $query, $values );
		}

		$stats = $wpdb->get_row( $query, ARRAY_A );

		if ( $stats ) {
			$stats['success_rate'] = $stats['total'] > 0
				? round( ( $stats['active'] / $stats['total'] ) * 100, 2 )
				: 0;
		}

		return $stats ?: array(
			'total'        => 0,
			'active'       => 0,
			'inactive'     => 0,
			'errors'       => 0,
			'success_rate' => 0,
		);
	}

	/**
	 * Validate verification data.
	 *
	 * @param array $data Verification data.
	 * @return array|WP_Error Validated data or error.
	 */
	public function validate_data( $data ) {
		$required_fields = array(
			'member_id'     => __( 'Member ID', 'gf-verifytx' ),
			'date_of_birth' => __( 'Date of Birth', 'gf-verifytx' ),
			'payer_id'      => __( 'Insurance Provider', 'gf-verifytx' ),
		);

		$errors = array();

		foreach ( $required_fields as $field => $label ) {
			if ( empty( $data[ $field ] ) ) {
				$errors[] = sprintf( __( '%s is required', 'gf-verifytx' ), $label );
			}
		}

		if ( ! empty( $data['date_of_birth'] ) ) {
			$date = strtotime( $data['date_of_birth'] );
			if ( ! $date || $date > time() ) {
				$errors[] = __( 'Invalid date of birth', 'gf-verifytx' );
			}
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors[] = __( 'Invalid email address', 'gf-verifytx' );
		}

		if ( ! empty( $data['phone'] ) ) {
			$phone_digits = preg_replace( '/[^0-9]/', '', $data['phone'] );
			if ( strlen( $phone_digits ) < 10 ) {
				$errors[] = __( 'Phone number must be at least 10 digits', 'gf-verifytx' );
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_failed', implode( ', ', $errors ), $errors );
		}

		return $data;
	}

	/**
	 * Format verification result for display.
	 *
	 * @param array $result Verification result.
	 * @return array Formatted result.
	 */
	public function format_for_display( $result ) {
		$formatted = array(
			'html'    => '',
			'text'    => '',
			'status'  => $result['status'] ?? 'unknown',
			'success' => $result['success'] ?? false,
		);

		if ( $result['success'] ) {
			$formatted['message'] = __( 'Insurance verified successfully', 'gf-verifytx' );

			if ( isset( $result['benefits'] ) ) {
				$formatted['coverage'] = $this->format_coverage_summary( $result['benefits'] );
			}
		} else {
			$formatted['message'] = isset( $result['error'] )
				? $result['error']
				: __( 'Unable to verify insurance', 'gf-verifytx' );
		}

		$formatted['html'] = $this->generate_result_html( $result );
		$formatted['text'] = $this->generate_result_text( $result );

		return apply_filters( 'gf_verifytx_format_display', $formatted, $result );
	}

	/**
	 * Format coverage summary.
	 *
	 * @param array $benefits Benefits data.
	 * @return array Coverage summary.
	 */
	private function format_coverage_summary( $benefits ) {
		$summary = array(
			'status' => 'Active',
		);

		foreach ( $benefits as $benefit ) {
			if ( isset( $benefit['in_network'] ) ) {
				$network = $benefit['in_network'];

				if ( isset( $network['copay'] ) && ! isset( $summary['copay'] ) ) {
					$summary['copay'] = $network['copay'];
				}

				if ( isset( $network['deductible'] ) && ! isset( $summary['deductible'] ) ) {
					$summary['deductible'] = $network['deductible'];
				}

				if ( isset( $network['out_of_pocket'] ) && ! isset( $summary['outOfPocket'] ) ) {
					$summary['outOfPocket'] = $network['out_of_pocket'];
				}
			}
		}

		return $summary;
	}

	/**
	 * Generate HTML for verification result.
	 *
	 * @param array $result Verification result.
	 * @return string HTML markup.
	 */
	private function generate_result_html( $result ) {
		ob_start();
		?>
		<div class="gf-verifytx-result <?php echo esc_attr( $result['success'] ? 'success' : 'error' ); ?>">
			<div class="result-header">
				<?php if ( $result['success'] ) : ?>
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Insurance Verified', 'gf-verifytx' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Verification Failed', 'gf-verifytx' ); ?>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $result['payer']['name'] ) ) : ?>
				<div class="result-payer">
					<strong><?php esc_html_e( 'Provider:', 'gf-verifytx' ); ?></strong>
					<?php echo esc_html( $result['payer']['name'] ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $result['status'] ) ) : ?>
				<div class="result-status">
					<strong><?php esc_html_e( 'Status:', 'gf-verifytx' ); ?></strong>
					<span class="status-<?php echo esc_attr( strtolower( $result['status'] ) ); ?>">
						<?php echo esc_html( ucfirst( $result['status'] ) ); ?>
					</span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $result['error'] ) ) : ?>
				<div class="result-error">
					<?php echo esc_html( $result['error'] ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Generate text for verification result.
	 *
	 * @param array $result Verification result.
	 * @return string Text summary.
	 */
	private function generate_result_text( $result ) {
		$lines = array();

		if ( $result['success'] ) {
			$lines[] = __( 'Insurance Verified Successfully', 'gf-verifytx' );
		} else {
			$lines[] = __( 'Insurance Verification Failed', 'gf-verifytx' );
		}

		if ( ! empty( $result['status'] ) ) {
			$lines[] = sprintf( __( 'Status: %s', 'gf-verifytx' ), ucfirst( $result['status'] ) );
		}

		if ( ! empty( $result['payer']['name'] ) ) {
			$lines[] = sprintf( __( 'Provider: %s', 'gf-verifytx' ), $result['payer']['name'] );
		}

		if ( ! empty( $result['error'] ) ) {
			$lines[] = sprintf( __( 'Error: %s', 'gf-verifytx' ), $result['error'] );
		}

		return implode( "\n", $lines );
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private function log_debug( $message ) {
		if ( class_exists( 'GF_VerifyTX' ) && method_exists( 'GF_VerifyTX', 'log_debug' ) ) {
			GF_VerifyTX::log_debug( '[Verification] ' . $message );
		}
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( class_exists( 'GF_VerifyTX' ) && method_exists( 'GF_VerifyTX', 'log_error' ) ) {
			GF_VerifyTX::log_error( '[Verification] ' . $message );
		}
		error_log( 'GF VerifyTX Verification: ' . $message );
	}
}