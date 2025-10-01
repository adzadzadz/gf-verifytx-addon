<?php
/**
 * Basic tests to verify setup
 *
 * @package GF_VerifyTX\Tests\Unit
 */

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Basic test class.
 */
class BasicTest extends TestCase {

	/**
	 * Test that PHPUnit is working.
	 */
	public function test_phpunit_works() {
		$this->assertTrue( true );
	}

	/**
	 * Test plugin constants are defined.
	 */
	public function test_plugin_constants_defined() {
		$this->assertTrue( defined( 'GF_VERIFYTX_PLUGIN_PATH' ) );
		$this->assertTrue( defined( 'GF_VERIFYTX_PLUGIN_FILE' ) );
		$this->assertTrue( defined( 'GF_VERIFYTX_VERSION' ) );
	}

	/**
	 * Test plugin files exist.
	 */
	public function test_plugin_files_exist() {
		$this->assertFileExists( GF_VERIFYTX_PLUGIN_PATH . 'gf-verifytx-addon.php' );
		$this->assertFileExists( GF_VERIFYTX_PLUGIN_PATH . 'class-gf-verifytx.php' );
		$this->assertFileExists( GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php' );
		$this->assertFileExists( GF_VERIFYTX_PLUGIN_PATH . 'includes/class-verification.php' );
		$this->assertFileExists( GF_VERIFYTX_PLUGIN_PATH . 'includes/class-field-verifytx.php' );
	}

	/**
	 * Test API client can be instantiated.
	 */
	public function test_api_client_instantiation() {
		require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php';
		$client = new \GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );
		$this->assertInstanceOf( \GF_VerifyTX_API_Client::class, $client );
	}

	/**
	 * Test WP_Error mock exists.
	 */
	public function test_wp_error_exists() {
		$this->assertTrue( class_exists( 'WP_Error' ) );
		$error = new \WP_Error( 'test_code', 'Test message' );
		$this->assertEquals( 'test_code', $error->get_error_code() );
		$this->assertEquals( 'Test message', $error->get_error_message() );
	}

	/**
	 * Test WordPress function mocks exist.
	 */
	public function test_wordpress_functions_exist() {
		$this->assertTrue( function_exists( '__' ) );
		$this->assertTrue( function_exists( 'esc_html' ) );
		$this->assertTrue( function_exists( 'esc_attr' ) );
		$this->assertTrue( function_exists( 'wp_json_encode' ) );

		$this->assertEquals( 'test', __( 'test', 'domain' ) );
		$this->assertEquals( 'test', esc_html( 'test' ) );
		$this->assertEquals( '{"test":true}', wp_json_encode( array( 'test' => true ) ) );
	}

	/**
	 * Test date formatting helper.
	 */
	public function test_date_formatting() {
		require_once GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php';
		$client = new \GF_VerifyTX_API_Client( 'test_id', 'test_secret', true );

		// Use reflection to test private method
		$reflection = new \ReflectionClass( $client );
		$method = $reflection->getMethod( 'format_date' );
		$method->setAccessible( true );

		$formatted = $method->invoke( $client, '2024-01-15' );
		$this->assertEquals( '01/15/2024', $formatted );
	}
}