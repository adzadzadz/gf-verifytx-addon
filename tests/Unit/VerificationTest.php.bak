<?php
/**
 * Unit tests for Verification Handler
 *
 * @package GF_VerifyTX\Tests\Unit
 */

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use GF_VerifyTX_Verification;
use GF_VerifyTX_API_Client;

/**
 * Verification handler test class.
 */
class VerificationTest extends TestCase {

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey::setUp();

		// Mock WordPress functions.
		Functions\when( 'current_time' )->justReturn( '2024-01-01 12:00:00' );
		Functions\when( 'get_option' )->justReturn( array(
			'cache_duration' => 24,
			'data_retention' => 90,
		) );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'do_action' )->justReturn( null );
		Functions\when( 'apply_filters' )->returnArg( 1 );
		Functions\when( 'is_wp_error' )->alias( function( $thing ) {
			return is_object( $thing ) && get_class( $thing ) === 'WP_Error';
		} );
		Functions\when( 'is_email' )->alias( function( $email ) {
			return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
		} );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_html_e' )->alias( function( $text ) {
			echo $text;
		} );

		// Mock database global.
		global $wpdb;
		$wpdb = Mockery::mock( 'wpdb' );
		$wpdb->prefix = 'wp_';
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( function( $query, ...$args ) {
			return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
		} );
		$wpdb->shouldReceive( 'get_var' )->andReturn( null );
		$wpdb->shouldReceive( 'insert' )->andReturn( 1 );
		$wpdb->shouldReceive( 'query' )->andReturn( 0 );
	}

	/**
	 * Tear down test fixtures.
	 */
	protected function tearDown(): void {
		Monkey::tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test successful insurance verification.
	 */
	public function test_verify_insurance_success() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$api_client->shouldReceive( 'create_and_verify_vob' )
			->once()
			->andReturn( array(
				'vob_id' => 'vob_123',
				'status' => 'Active',
				'verified' => true,
				'payer' => array(
					'id' => 'BCBS',
					'name' => 'Blue Cross Blue Shield',
				),
				'benefits' => array(
					array(
						'type' => 'medical',
						'in_network' => array(
							'copay' => 25,
						),
					),
				),
			) );

		$verification = new GF_VerifyTX_Verification( $api_client );

		$data = array(
			'member_id' => 'MEM123',
			'date_of_birth' => '01/01/1990',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'payer_id' => 'BCBS',
		);

		$result = $verification->verify_insurance( $data, 1, 1 );

		$this->assertTrue( $result['success'] );
		$this->assertTrue( $result['verified'] );
		$this->assertEquals( 'Active', $result['status'] );
		$this->assertEquals( 'vob_123', $result['vob_id'] );
		$this->assertArrayHasKey( 'payer', $result );
		$this->assertArrayHasKey( 'benefits', $result );
	}

	/**
	 * Test failed insurance verification.
	 */
	public function test_verify_insurance_failure() {
		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Invalid member ID' );
		$wp_error->shouldReceive( 'get_error_code' )
			->andReturn( 'INVALID_MEMBER' );

		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$api_client->shouldReceive( 'create_and_verify_vob' )
			->once()
			->andReturn( $wp_error );

		$verification = new GF_VerifyTX_Verification( $api_client );

		$data = array(
			'member_id' => 'INVALID',
			'date_of_birth' => '01/01/1990',
			'payer_id' => 'BCBS',
		);

		$result = $verification->verify_insurance( $data, 1, 1 );

		$this->assertFalse( $result['success'] );
		$this->assertFalse( $result['verified'] );
		$this->assertEquals( 'error', $result['status'] );
		$this->assertEquals( 'Invalid member ID', $result['error'] );
		$this->assertEquals( 'INVALID_MEMBER', $result['error_code'] );
	}

	/**
	 * Test data validation with missing required fields.
	 */
	public function test_validate_data_missing_fields() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$data = array(
			'first_name' => 'John',
			'last_name' => 'Doe',
			// Missing required fields.
		);

		$result = $verification->validate_data( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test data validation with valid data.
	 */
	public function test_validate_data_success() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$data = array(
			'member_id' => 'MEM123',
			'date_of_birth' => '01/01/1990',
			'payer_id' => 'BCBS',
			'email' => 'test@example.com',
			'phone' => '555-123-4567',
		);

		$result = $verification->validate_data( $data );

		$this->assertIsArray( $result );
		$this->assertEquals( $data, $result );
	}

	/**
	 * Test data validation with invalid email.
	 */
	public function test_validate_data_invalid_email() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$data = array(
			'member_id' => 'MEM123',
			'date_of_birth' => '01/01/1990',
			'payer_id' => 'BCBS',
			'email' => 'invalid-email',
		);

		$result = $verification->validate_data( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test verification statistics calculation.
	 */
	public function test_get_verification_stats() {
		global $wpdb;
		$wpdb->shouldReceive( 'get_row' )
			->once()
			->andReturn( array(
				'total' => 100,
				'active' => 75,
				'inactive' => 20,
				'errors' => 5,
			) );

		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$stats = $verification->get_verification_stats( 1, 'month' );

		$this->assertEquals( 100, $stats['total'] );
		$this->assertEquals( 75, $stats['active'] );
		$this->assertEquals( 20, $stats['inactive'] );
		$this->assertEquals( 5, $stats['errors'] );
		$this->assertEquals( 75.0, $stats['success_rate'] );
	}

	/**
	 * Test format for display with successful result.
	 */
	public function test_format_for_display_success() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$result = array(
			'success' => true,
			'status' => 'Active',
			'benefits' => array(
				array(
					'in_network' => array(
						'copay' => 25,
						'deductible' => array(
							'amount' => 1000,
							'met' => 500,
							'remaining' => 500,
						),
					),
				),
			),
		);

		$formatted = $verification->format_for_display( $result );

		$this->assertTrue( $formatted['success'] );
		$this->assertEquals( 'Active', $formatted['status'] );
		$this->assertEquals( 'Insurance verified successfully', $formatted['message'] );
		$this->assertArrayHasKey( 'coverage', $formatted );
		$this->assertArrayHasKey( 'html', $formatted );
		$this->assertArrayHasKey( 'text', $formatted );
	}

	/**
	 * Test format for display with failed result.
	 */
	public function test_format_for_display_failure() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$result = array(
			'success' => false,
			'status' => 'error',
			'error' => 'Member not found',
		);

		$formatted = $verification->format_for_display( $result );

		$this->assertFalse( $formatted['success'] );
		$this->assertEquals( 'error', $formatted['status'] );
		$this->assertEquals( 'Member not found', $formatted['message'] );
		$this->assertArrayHasKey( 'html', $formatted );
		$this->assertArrayHasKey( 'text', $formatted );
	}

	/**
	 * Test entry verification history retrieval.
	 */
	public function test_get_entry_verification_history() {
		global $wpdb;
		$wpdb->shouldReceive( 'get_results' )
			->once()
			->andReturn( array(
				array(
					'id' => 1,
					'entry_id' => 123,
					'form_id' => 1,
					'verification_date' => '2024-01-01 10:00:00',
					'request_data' => '{"member_id":"MEM123"}',
					'response_data' => '{"status":"Active"}',
					'status' => 'Active',
				),
				array(
					'id' => 2,
					'entry_id' => 123,
					'form_id' => 1,
					'verification_date' => '2024-01-02 10:00:00',
					'request_data' => '{"member_id":"MEM123"}',
					'response_data' => '{"status":"Active"}',
					'status' => 'Active',
				),
			) );

		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$history = $verification->get_entry_verification_history( 123 );

		$this->assertIsArray( $history );
		$this->assertCount( 2, $history );
		$this->assertEquals( 123, $history[0]['entry_id'] );
		$this->assertIsArray( $history[0]['request_data'] );
		$this->assertIsArray( $history[0]['response_data'] );
	}

	/**
	 * Test cache key generation.
	 */
	public function test_generate_cache_key() {
		$api_client = Mockery::mock( GF_VerifyTX_API_Client::class );
		$verification = new GF_VerifyTX_Verification( $api_client );

		$reflection = new \ReflectionClass( $verification );
		$method = $reflection->getMethod( 'generate_cache_key' );
		$method->setAccessible( true );

		$data1 = array(
			'member_id' => 'MEM123',
			'payer_id' => 'BCBS',
			'date_of_birth' => '01/01/1990',
		);

		$data2 = array(
			'member_id' => 'MEM123',
			'payer_id' => 'BCBS',
			'date_of_birth' => '01/01/1990',
		);

		$data3 = array(
			'member_id' => 'MEM456',
			'payer_id' => 'AETNA',
			'date_of_birth' => '02/02/1985',
		);

		$key1 = $method->invoke( $verification, $data1 );
		$key2 = $method->invoke( $verification, $data2 );
		$key3 = $method->invoke( $verification, $data3 );

		// Same data should produce same key.
		$this->assertEquals( $key1, $key2 );
		// Different data should produce different key.
		$this->assertNotEquals( $key1, $key3 );
		// Key should start with prefix.
		$this->assertStringStartsWith( 'vtx_', $key1 );
	}
}