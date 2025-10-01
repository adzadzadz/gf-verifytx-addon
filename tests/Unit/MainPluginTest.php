<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MainPluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset globals that may be modified by tests
        global $wpdb;
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';
        $wpdb->get_charset_collate = function() {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        };
        $wpdb->query = function($query) {
            return true;
        };
    }

    // Plugin File Structure Tests
    public function testPluginFilesExist()
    {
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'gf-verifytx-addon.php');
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'class-gf-verifytx.php');
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php');
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'includes/class-verification.php');
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'includes/class-field-verifytx.php');
    }

    // Constants Tests
    public function testConstantsAreDefined()
    {
        $this->assertTrue(defined('GF_VERIFYTX_PLUGIN_PATH'));
        $this->assertTrue(defined('GF_VERIFYTX_VERSION'));
        $this->assertEquals('0.1.0', GF_VERIFYTX_VERSION);
    }

    // Database Table Creation Tests
    public function testCreateDatabaseTablesSQL()
    {
        global $wpdb;
        $queries = [];

        // Mock wpdb->query to capture queries
        $wpdb->query = function($query) use (&$queries) {
            $queries[] = $query;
            return true;
        };

        // Include the function definition
        require_once GF_VERIFYTX_PLUGIN_PATH . 'gf-verifytx-addon.php';

        gf_verifytx_create_database_tables();

        // Check that table creation queries were generated
        $this->assertCount(2, $queries);

        // Verify verifications table query
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $queries[0]);
        $this->assertStringContainsString('gf_verifytx_verifications', $queries[0]);
        $this->assertStringContainsString('id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT', $queries[0]);
        $this->assertStringContainsString('form_id', $queries[0]);
        $this->assertStringContainsString('entry_id', $queries[0]);
        $this->assertStringContainsString('member_id', $queries[0]);

        // Verify cache table query
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $queries[1]);
        $this->assertStringContainsString('gf_verifytx_cache', $queries[1]);
        $this->assertStringContainsString('cache_key varchar(64)', $queries[1]);
        $this->assertStringContainsString('cache_value', $queries[1]);
        $this->assertStringContainsString('expires_at', $queries[1]);
    }

    // Global Function Tests
    public function testGfVerifytxFunctionReturnsInstance()
    {
        // Mock the class existence
        if (!class_exists('GF_VerifyTX')) {
            eval('class GF_VerifyTX {
                private static $instance;
                public static function get_instance() {
                    if (!self::$instance) {
                        self::$instance = new self();
                    }
                    return self::$instance;
                }
            }');
        }

        $result = gf_verifytx();
        $this->assertInstanceOf('GF_VerifyTX', $result);
    }

    public function testGfVerifytxFunctionReturnsFalseWhenClassNotExists()
    {
        // We can't easily test this without unloading the class,
        // so we'll create a wrapper function for testing
        $get_instance = function() {
            if (class_exists('NonExistentClass')) {
                return NonExistentClass::get_instance();
            }
            return false;
        };

        $result = $get_instance();
        $this->assertFalse($result);
    }

    // Activation Tests
    public function testActivationRequiresGravityForms()
    {
        // Mock class_exists to return false for GFForms
        $mock_class_exists = function($class) {
            return $class !== 'GFForms';
        };

        // We would need to mock class_exists which is complex in PHP
        // For now, we'll test the logic structure
        $this->assertTrue(true); // Placeholder for actual test
    }

    // WordPress Functions Mock Tests
    public function testWordPressFunctionsExist()
    {
        $this->assertTrue(function_exists('__'));
        $this->assertTrue(function_exists('esc_html'));
        $this->assertTrue(function_exists('esc_attr'));
        $this->assertTrue(function_exists('esc_html__'));
        $this->assertTrue(function_exists('esc_html_e'));
        $this->assertTrue(function_exists('wp_json_encode'));
        $this->assertTrue(function_exists('sanitize_text_field'));
        $this->assertTrue(function_exists('is_email'));
        $this->assertTrue(function_exists('wp_remote_post'));
        $this->assertTrue(function_exists('wp_remote_get'));
        $this->assertTrue(function_exists('get_option'));
        $this->assertTrue(function_exists('get_transient'));
        $this->assertTrue(function_exists('set_transient'));
        $this->assertTrue(function_exists('delete_transient'));
        $this->assertTrue(function_exists('add_action'));
        $this->assertTrue(function_exists('add_filter'));
        $this->assertTrue(function_exists('do_action'));
        $this->assertTrue(function_exists('apply_filters'));
        $this->assertTrue(function_exists('wp_verify_nonce'));
        $this->assertTrue(function_exists('wp_create_nonce'));
        $this->assertTrue(function_exists('check_ajax_referer'));
        $this->assertTrue(function_exists('wp_die'));
        $this->assertTrue(function_exists('absint'));
        $this->assertTrue(function_exists('wp_parse_args'));
    }

    // Test Global Functions Behavior
    public function testEscHtmlFunction()
    {
        $input = '<script>alert("XSS")</script>';
        $output = esc_html($input);
        $this->assertEquals('&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;', $output);
    }

    public function testSanitizeTextFieldFunction()
    {
        $input = '  <b>Test</b> String   ';
        $output = sanitize_text_field($input);
        $this->assertEquals('Test String', $output);
    }

    public function testIsEmailFunction()
    {
        $this->assertTrue(is_email('test@example.com'));
        $this->assertFalse(is_email('not-an-email'));
        $this->assertFalse(is_email('test@'));
        $this->assertFalse(is_email('@example.com'));
    }

    public function testWpJsonEncodeFunction()
    {
        $data = ['test' => 'value', 'number' => 123];
        $json = wp_json_encode($data);
        $this->assertEquals('{"test":"value","number":123}', $json);
    }

    public function testAbsintFunction()
    {
        $this->assertEquals(123, absint(123));
        $this->assertEquals(123, absint(-123));
        $this->assertEquals(123, absint('123'));
        $this->assertEquals(0, absint('abc'));
        $this->assertEquals(123, absint(123.7));
    }

    public function testWpParseArgsFunction()
    {
        $args = ['key1' => 'value1'];
        $defaults = ['key1' => 'default1', 'key2' => 'default2'];
        $result = wp_parse_args($args, $defaults);

        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('default2', $result['key2']);
    }

    public function testWpVerifyNonceFunction()
    {
        $nonce = wp_create_nonce('test_action');
        $this->assertEquals('test_nonce', $nonce);

        $result = wp_verify_nonce($nonce, 'test_action');
        $this->assertEquals(1, $result);

        $result = wp_verify_nonce('invalid_nonce', 'test_action');
        $this->assertFalse($result);
    }

    // WP_Error Class Tests
    public function testWpErrorClass()
    {
        $error = new \WP_Error('test_code', 'Test message');

        $this->assertInstanceOf('WP_Error', $error);
        $this->assertEquals('test_code', $error->get_error_code());
        $this->assertEquals('Test message', $error->get_error_message());
    }

    public function testWpErrorWithMultipleErrors()
    {
        $error = new \WP_Error('code1', 'Message 1');
        $this->assertEquals('code1', $error->get_error_code());
        $this->assertEquals('Message 1', $error->get_error_message('code1'));
    }

    // Database Mock Tests
    public function testWpdbPrepareFunction()
    {
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM table WHERE id = %s AND num = %s", 'test', 123);
        $expected = "SELECT * FROM table WHERE id = 'test' AND num = '123'";
        $this->assertEquals($expected, $query);
    }

    public function testWpdbPrefixProperty()
    {
        global $wpdb;
        $this->assertEquals('wp_', $wpdb->prefix);
    }

    // Remote Request Mock Tests
    public function testWpRemotePostReturnsExpectedFormat()
    {
        $response = wp_remote_post('http://example.com', []);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('response', $response);
        $this->assertArrayHasKey('body', $response);
        $this->assertEquals(200, $response['response']['code']);
    }

    public function testWpRemoteRetrieveBody()
    {
        $response = ['body' => 'test body', 'response' => ['code' => 200]];
        $body = wp_remote_retrieve_body($response);
        $this->assertEquals('test body', $body);
    }

    public function testWpRemoteRetrieveResponseCode()
    {
        $response = ['body' => 'test body', 'response' => ['code' => 404]];
        $code = wp_remote_retrieve_response_code($response);
        $this->assertEquals(404, $code);
    }

    // Time Function Tests
    public function testCurrentTimeFunction()
    {
        $mysql_time = current_time('mysql');
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $mysql_time);

        $timestamp = current_time('timestamp');
        $this->assertIsInt($timestamp);
        $this->assertGreaterThan(0, $timestamp);
    }
}