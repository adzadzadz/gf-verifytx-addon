<?php
/**
 * VerifyTX API Client
 *
 * @package GF_VerifyTX
 */

defined( 'ABSPATH' ) || exit;

/**
 * API Client for VerifyTX service.
 */
class GF_VerifyTX_API_Client {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_url;

	/**
	 * OAuth client ID.
	 *
	 * @var string
	 */
	private $client_id;

	/**
	 * OAuth client secret.
	 *
	 * @var string
	 */
	private $client_secret;

	/**
	 * Access token.
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Token expiry time.
	 *
	 * @var int
	 */
	private $token_expires;

	/**
	 * Test mode flag.
	 *
	 * @var bool
	 */
	private $test_mode;

	/**
	 * Constructor.
	 *
	 * @param string $client_id     OAuth client ID.
	 * @param string $client_secret OAuth client secret.
	 * @param bool   $test_mode     Whether to use test environment.
	 */
	public function __construct( $client_id, $client_secret, $test_mode = false ) {
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
		$this->test_mode     = $test_mode;

		$this->api_url = $test_mode
			? 'https://sandbox.api.verifytx.com'
			: 'https://api.verifytx.com';

		$this->load_cached_token();
	}

	/**
	 * Get OAuth access token.
	 *
	 * @return string|WP_Error Access token or error.
	 */
	private function get_access_token() {
		if ( $this->access_token && $this->token_expires > time() ) {
			return $this->access_token;
		}

		$response = wp_remote_post(
			$this->api_url . '/oauth/token',
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $this->client_id,
					'client_secret' => $this->client_secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'OAuth token request failed: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error_msg = isset( $body['error_description'] ) ? $body['error_description'] : 'Failed to obtain access token';
			$this->log_error( 'OAuth token error: ' . $error_msg );
			return new WP_Error( 'auth_failed', $error_msg );
		}

		$this->access_token  = $body['access_token'];
		$this->token_expires = time() + ( isset( $body['expires_in'] ) ? $body['expires_in'] : 3600 );

		$this->cache_token();

		return $this->access_token;
	}

	/**
	 * Create and verify a VOB (Verification of Benefits).
	 *
	 * @param array $data VOB data.
	 * @return array|WP_Error Response data or error.
	 */
	public function create_and_verify_vob( $data ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$required_fields = array(
			'client_type'         => 'prospect',
			'subscriber_relation' => $this->map_relationship( $data['relationship'] ?? 'self' ),
			'payer_id'           => $data['payer_id'] ?? '',
			'payer_name'         => $data['payer_name'] ?? '',
			'member_id'          => $data['member_id'] ?? '',
			'date_of_birth'      => $this->format_date( $data['date_of_birth'] ?? '' ),
			'as_of_date'         => date( 'Y-m-d' ),
		);

		$optional_fields = array(
			'first_name'      => $data['first_name'] ?? '',
			'last_name'       => $data['last_name'] ?? '',
			'gender'          => $data['gender'] ?? '',
			'phone'           => $this->format_phone( $data['phone'] ?? '' ),
			'email'           => $data['email'] ?? '',
			'group_id'        => $data['group_number'] ?? '',
			'facility'        => $data['facility_id'] ?? '',
			'insurance_phone' => $this->format_phone( $data['insurance_phone'] ?? '' ),
		);

		$request_body = array_merge(
			$required_fields,
			array_filter( $optional_fields )
		);

		$response = wp_remote_post(
			$this->api_url . '/vobs/verify',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'VOB verification request failed: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 && $code !== 201 ) {
			$error_msg = $this->parse_error_message( $body );
			$this->log_error( 'VOB verification error: ' . $error_msg );
			return new WP_Error( 'verification_failed', $error_msg, $body );
		}

		$this->log_debug( 'VOB verification successful for member: ' . $data['member_id'] );

		return $this->parse_verification_response( $body );
	}

	/**
	 * Create a VOB without verifying.
	 *
	 * @param array $data VOB data.
	 * @return array|WP_Error Response data or error.
	 */
	public function create_vob( $data ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$request_body = $this->prepare_vob_data( $data );

		$response = wp_remote_post(
			$this->api_url . '/vobs',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'VOB creation request failed: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 && $code !== 201 ) {
			$error_msg = $this->parse_error_message( $body );
			$this->log_error( 'VOB creation error: ' . $error_msg );
			return new WP_Error( 'creation_failed', $error_msg, $body );
		}

		return $body;
	}

	/**
	 * Reverify an existing VOB.
	 *
	 * @param string $vob_id VOB ID.
	 * @return array|WP_Error Response data or error.
	 */
	public function reverify_vob( $vob_id ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			$this->api_url . '/vobs/verify',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( '_id' => $vob_id ) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'VOB reverification request failed: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$error_msg = $this->parse_error_message( $body );
			$this->log_error( 'VOB reverification error: ' . $error_msg );
			return new WP_Error( 'reverification_failed', $error_msg, $body );
		}

		return $this->parse_verification_response( $body );
	}

	/**
	 * Get VOB details.
	 *
	 * @param string $vob_id VOB ID.
	 * @return array|WP_Error Response data or error.
	 */
	public function get_vob( $vob_id ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			$this->api_url . '/vobs/' . $vob_id,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'VOB retrieval request failed: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$error_msg = $this->parse_error_message( $body );
			$this->log_error( 'VOB retrieval error: ' . $error_msg );
			return new WP_Error( 'retrieval_failed', $error_msg, $body );
		}

		return $body;
	}

	/**
	 * List payers.
	 *
	 * @param string $search Search query.
	 * @param int    $limit  Result limit.
	 * @return array|WP_Error Response data or error.
	 */
	public function list_payers( $search = '', $limit = 100 ) {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$params = array( 'limit' => $limit );
		if ( ! empty( $search ) ) {
			$params['q'] = $search;
		}

		$response = wp_remote_get(
			add_query_arg( $params, $this->api_url . '/payers' ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Payers list request failed: ' . $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$error_msg = $this->parse_error_message( $body );
			$this->log_error( 'Payers list error: ' . $error_msg );
			return new WP_Error( 'payers_failed', $error_msg, $body );
		}

		return $body;
	}

	/**
	 * Test API connection.
	 *
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function test_connection() {
		$token = $this->get_access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			$this->api_url . '/users/me',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg = $this->parse_error_message( $body );
			return new WP_Error( 'connection_failed', $error_msg );
		}

		return true;
	}

	/**
	 * Parse verification response.
	 *
	 * @param array $response API response.
	 * @return array Parsed response.
	 */
	private function parse_verification_response( $response ) {
		$parsed = array(
			'vob_id'     => $response['_id'] ?? '',
			'status'     => $response['status'] ?? 'unknown',
			'verified'   => ! empty( $response['status'] ) && $response['status'] === 'Active',
			'error'      => $response['error'] ?? null,
			'error_ref'  => $response['error_ref'] ?? null,
			'as_of_date' => $response['as_of_date'] ?? '',
		);

		if ( ! empty( $response['benefits'] ) ) {
			$parsed['benefits'] = $this->parse_benefits( $response['benefits'] );
		}

		if ( ! empty( $response['plans'] ) ) {
			$parsed['plans'] = $response['plans'];
		}

		if ( ! empty( $response['subscriber_details'] ) ) {
			$parsed['subscriber'] = array(
				'first_name' => $response['subscriber_details']['first_name'] ?? '',
				'last_name'  => $response['subscriber_details']['last_name'] ?? '',
				'member_id'  => $response['subscriber_details']['member_id'] ?? '',
			);
		}

		if ( ! empty( $response['payer_name'] ) ) {
			$parsed['payer'] = array(
				'id'   => $response['payer_id'] ?? '',
				'name' => $response['payer_name'],
			);
		}

		return $parsed;
	}

	/**
	 * Parse benefits data.
	 *
	 * @param array $benefits Benefits array from API.
	 * @return array Parsed benefits.
	 */
	private function parse_benefits( $benefits ) {
		$parsed = array();

		foreach ( $benefits as $benefit ) {
			$type = $benefit['type'] ?? 'unknown';

			$parsed_benefit = array(
				'type'        => $type,
				'in_network'  => array(),
				'out_network' => array(),
			);

			if ( isset( $benefit['in_network'] ) ) {
				$parsed_benefit['in_network'] = $this->parse_network_benefits( $benefit['in_network'] );
			}

			if ( isset( $benefit['out_network'] ) ) {
				$parsed_benefit['out_network'] = $this->parse_network_benefits( $benefit['out_network'] );
			}

			$parsed[] = $parsed_benefit;
		}

		return $parsed;
	}

	/**
	 * Parse network benefits.
	 *
	 * @param array $network Network benefits data.
	 * @return array Parsed network benefits.
	 */
	private function parse_network_benefits( $network ) {
		$parsed = array();

		if ( isset( $network['copay'] ) ) {
			$parsed['copay'] = $network['copay'];
		}

		if ( isset( $network['deductible'] ) ) {
			$parsed['deductible'] = array(
				'amount'    => $network['deductible']['amount'] ?? 0,
				'met'       => $network['deductible']['met'] ?? 0,
				'remaining' => $network['deductible']['remaining'] ?? 0,
			);
		}

		if ( isset( $network['out_of_pocket'] ) ) {
			$parsed['out_of_pocket'] = array(
				'max'       => $network['out_of_pocket']['max'] ?? 0,
				'met'       => $network['out_of_pocket']['met'] ?? 0,
				'remaining' => $network['out_of_pocket']['remaining'] ?? 0,
			);
		}

		if ( isset( $network['coinsurance'] ) ) {
			$parsed['coinsurance'] = $network['coinsurance'];
		}

		return $parsed;
	}

	/**
	 * Prepare VOB data for API request.
	 *
	 * @param array $data Raw data.
	 * @return array Prepared data.
	 */
	private function prepare_vob_data( $data ) {
		return array(
			'client_type'         => 'prospect',
			'subscriber_relation' => $this->map_relationship( $data['relationship'] ?? 'self' ),
			'payer_id'           => $data['payer_id'] ?? '',
			'payer_name'         => $data['payer_name'] ?? '',
			'member_id'          => $data['member_id'] ?? '',
			'date_of_birth'      => $this->format_date( $data['date_of_birth'] ?? '' ),
			'first_name'         => $data['first_name'] ?? '',
			'last_name'          => $data['last_name'] ?? '',
			'gender'             => $data['gender'] ?? '',
			'phone'              => $this->format_phone( $data['phone'] ?? '' ),
			'email'              => $data['email'] ?? '',
			'group_id'           => $data['group_number'] ?? '',
		);
	}

	/**
	 * Map relationship to API code.
	 *
	 * @param string $relationship Relationship string.
	 * @return string API relationship code.
	 */
	private function map_relationship( $relationship ) {
		$map = array(
			'self'   => 'SR01',
			'spouse' => 'SR02',
			'child'  => 'SR03',
			'other'  => 'SR04',
			'adult'  => 'SR05',
		);

		$lower = strtolower( $relationship );
		return $map[ $lower ] ?? 'SR01';
	}

	/**
	 * Format date for API.
	 *
	 * @param string $date Date string.
	 * @return string Formatted date (MM/DD/YYYY).
	 */
	private function format_date( $date ) {
		if ( empty( $date ) ) {
			return '';
		}

		$timestamp = strtotime( $date );
		if ( ! $timestamp ) {
			return $date;
		}

		return date( 'm/d/Y', $timestamp );
	}

	/**
	 * Format phone number.
	 *
	 * @param string $phone Phone number.
	 * @return string Formatted phone (digits only).
	 */
	private function format_phone( $phone ) {
		return preg_replace( '/[^0-9]/', '', $phone );
	}

	/**
	 * Parse error message from response.
	 *
	 * @param array $body Response body.
	 * @return string Error message.
	 */
	private function parse_error_message( $body ) {
		if ( isset( $body['error'] ) ) {
			if ( is_string( $body['error'] ) ) {
				return $body['error'];
			}
			if ( isset( $body['error']['message'] ) ) {
				return $body['error']['message'];
			}
		}

		if ( isset( $body['message'] ) ) {
			return $body['message'];
		}

		if ( isset( $body['error_description'] ) ) {
			return $body['error_description'];
		}

		return __( 'An unknown error occurred', 'gf-verifytx' );
	}

	/**
	 * Cache access token.
	 */
	private function cache_token() {
		$cache_key = 'gf_verifytx_token_' . md5( $this->client_id );
		$cache_data = array(
			'token'   => $this->access_token,
			'expires' => $this->token_expires,
		);

		set_transient( $cache_key, $cache_data, $this->token_expires - time() );
	}

	/**
	 * Load cached token.
	 */
	private function load_cached_token() {
		$cache_key = 'gf_verifytx_token_' . md5( $this->client_id );
		$cached = get_transient( $cache_key );

		if ( $cached && isset( $cached['token'] ) && isset( $cached['expires'] ) ) {
			if ( $cached['expires'] > time() ) {
				$this->access_token = $cached['token'];
				$this->token_expires = $cached['expires'];
			}
		}
	}

	/**
	 * Log debug message.
	 *
	 * @param string $message Message to log.
	 */
	private function log_debug( $message ) {
		if ( class_exists( 'GF_VerifyTX' ) && method_exists( 'GF_VerifyTX', 'log_debug' ) ) {
			GF_VerifyTX::log_debug( $message );
		}
	}

	/**
	 * Log error message.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( class_exists( 'GF_VerifyTX' ) && method_exists( 'GF_VerifyTX', 'log_error' ) ) {
			GF_VerifyTX::log_error( $message );
		}
		error_log( 'GF VerifyTX: ' . $message );
	}
}