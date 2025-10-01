<?php
/**
 * Unit tests for API Client
 *
 * @package GF_VerifyTX\Tests\Unit
 */

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use GF_VerifyTX_API_Client;

/**
 * API Client test class.
 */
class ApiClientTest extends TestCase {

	/**
	 * API client instance.
	 *
	 * @var GF_VerifyTX_API_Client
	 */
	private $api_client;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey::setUp();

		// Mock WordPress functions.
		Functions\when( 'current_time' )->justReturn( '2024-01-01 12:00:00' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
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
	 * Test API client instantiation.
	 */
	public function test_api_client_instantiation() {
		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$this->assertInstanceOf( GF_VerifyTX_API_Client::class, $client );
	}

	/**
	 * Test successful OAuth token retrieval.
	 */
	public function test_get_access_token_success() {
		// Mock successful token response.
		Functions\when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 200,
			),
			'body' => json_encode( array(
				'access_token' => 'test_token_123',
				'expires_in' => 3600,
				'token_type' => 'Bearer',
			) ),
		) );

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode( array(
				'access_token' => 'test_token_123',
				'expires_in' => 3600,
				'token_type' => 'Bearer',
			) )
		);

		Functions\when( 'is_wp_error' )->justReturn( false );

		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'get_access_token' );
		$method->setAccessible( true );

		$token = $method->invoke( $client );
		$this->assertEquals( 'test_token_123', $token );
	}

	/**
	 * Test failed OAuth token retrieval.
	 */
	public function test_get_access_token_failure() {
		// Create mock WP_Error.
		$wp_error = Mockery::mock( 'WP_Error' );
		$wp_error->shouldReceive( 'get_error_message' )
			->andReturn( 'Authentication failed' );

		Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'get_access_token' );
		$method->setAccessible( true );

		$result = $method->invoke( $client );
		$this->assertEquals( $wp_error, $result );
	}

	/**
	 * Test VOB creation and verification.
	 */
	public function test_create_and_verify_vob_success() {
		// Mock successful token.
		$this->mock_successful_token();

		// Mock successful verification response.
		Functions\when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 200,
			),
			'body' => json_encode( array(
				'_id' => 'vob_123',
				'status' => 'Active',
				'verified' => true,
				'payer_id' => 'BCBS',
				'payer_name' => 'Blue Cross Blue Shield',
				'member_id' => 'MEM123',
				'benefits' => array(
					array(
						'type' => 'medical',
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
			) ),
		) );

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode( array(
				'_id' => 'vob_123',
				'status' => 'Active',
				'verified' => true,
				'vob_id' => 'vob_123',
			) )
		);

		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );

		$data = array(
			'member_id' => 'MEM123',
			'date_of_birth' => '01/01/1990',
			'first_name' => 'John',
			'last_name' => 'Doe',
			'payer_id' => 'BCBS',
			'payer_name' => 'Blue Cross Blue Shield',
		);

		$result = $client->create_and_verify_vob( $data );

		$this->assertIsArray( $result );
		$this->assertEquals( 'vob_123', $result['vob_id'] );
		$this->assertEquals( 'Active', $result['status'] );
		$this->assertTrue( $result['verified'] );
	}

	/**
	 * Test VOB verification with invalid data.
	 */
	public function test_create_and_verify_vob_invalid_data() {
		$this->mock_successful_token();

		// Mock error response.
		Functions\when( 'wp_remote_post' )->justReturn( array(
			'response' => array(
				'code' => 400,
			),
			'body' => json_encode( array(
				'error' => 'Invalid member ID',
				'error_code' => 'INVALID_MEMBER',
			) ),
		) );

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 400 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode( array(
				'error' => 'Invalid member ID',
			) )
		);

		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );

		$data = array(
			'member_id' => 'INVALID',
			'date_of_birth' => '01/01/1990',
			'payer_id' => 'BCBS',
		);

		$result = $client->create_and_verify_vob( $data );

		$this->assertInstanceOf( 'WP_Error', $result );
	}

	/**
	 * Test connection test method.
	 */
	public function test_connection_success() {
		$this->mock_successful_token();

		Functions\when( 'wp_remote_get' )->justReturn( array(
			'response' => array(
				'code' => 200,
			),
			'body' => json_encode( array(
				'id' => 'user_123',
				'email' => 'test@example.com',
				'account' => 'Test Account',
			) ),
		) );

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$result = $client->test_connection();

		$this->assertTrue( $result );
	}

	/**
	 * Test payer list retrieval.
	 */
	public function test_list_payers() {
		$this->mock_successful_token();

		Functions\when( 'wp_remote_get' )->justReturn( array(
			'response' => array(
				'code' => 200,
			),
			'body' => json_encode( array(
				'payers' => array(
					array(
						'id' => 'BCBS',
						'name' => 'Blue Cross Blue Shield',
						'supported' => true,
					),
					array(
						'id' => 'AETNA',
						'name' => 'Aetna',
						'supported' => true,
					),
				),
			) ),
		) );

		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn(
			json_encode( array(
				'payers' => array(
					array( 'id' => 'BCBS', 'name' => 'Blue Cross Blue Shield' ),
					array( 'id' => 'AETNA', 'name' => 'Aetna' ),
				),
			) )
		);

		Functions\when( 'add_query_arg' )->alias( function( $args, $url ) {
			return $url . '?' . http_build_query( $args );
		} );

		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$result = $client->list_payers( 'blue', 10 );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'payers', $result );
	}

	/**
	 * Test date formatting.
	 */
	public function test_format_date() {
		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'format_date' );
		$method->setAccessible( true );

		// Test various date formats.
		$this->assertEquals( '01/15/1990', $method->invoke( $client, '1990-01-15' ) );
		$this->assertEquals( '12/25/2000', $method->invoke( $client, '2000-12-25' ) );
		$this->assertEquals( '03/04/1991', $method->invoke( $client, '03/04/1991' ) ); // Already formatted.
		$this->assertEquals( '', $method->invoke( $client, '' ) ); // Empty string.
	}

	/**
	 * Test phone formatting.
	 */
	public function test_format_phone() {
		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'format_phone' );
		$method->setAccessible( true );

		// Test various phone formats.
		$this->assertEquals( '5551234567', $method->invoke( $client, '(555) 123-4567' ) );
		$this->assertEquals( '5551234567', $method->invoke( $client, '555-123-4567' ) );
		$this->assertEquals( '5551234567', $method->invoke( $client, '555.123.4567' ) );
		$this->assertEquals( '5551234567', $method->invoke( $client, '5551234567' ) );
	}

	/**
	 * Test relationship mapping.
	 */
	public function test_map_relationship() {
		$client = new GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'map_relationship' );
		$method->setAccessible( true );

		$this->assertEquals( 'SR01', $method->invoke( $client, 'self' ) );
		$this->assertEquals( 'SR02', $method->invoke( $client, 'spouse' ) );
		$this->assertEquals( 'SR03', $method->invoke( $client, 'child' ) );
		$this->assertEquals( 'SR04', $method->invoke( $client, 'other' ) );
		$this->assertEquals( 'SR05', $method->invoke( $client, 'adult' ) );
		$this->assertEquals( 'SR01', $method->invoke( $client, 'unknown' ) ); // Default.
	}

	/**
	 * Mock successful token retrieval.
	 */
	private function mock_successful_token() {
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'get_transient' )->justReturn( array(
			'token' => 'test_token_123',
			'expires' => time() + 3600,
		) );
	}
}