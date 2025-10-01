<?php
/**
 * Simplest possible test
 */

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SimpleTest extends TestCase {

	public function test_basic_assertion() {
		$this->assertTrue( true );
	}

	public function test_constants() {
		$this->assertTrue( defined( 'GF_VERIFYTX_PLUGIN_PATH' ) );
	}

	public function test_php_version() {
		$this->assertTrue( version_compare( PHP_VERSION, '7.4.0', '>=' ) );
	}
}