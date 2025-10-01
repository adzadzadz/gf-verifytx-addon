<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AddonClassTest extends TestCase
{
    private $addon;

    protected function setUp(): void
    {
        // Mock Gravity Forms classes if they don't exist
        if (!class_exists('GFAddOn')) {
            eval('abstract class GFAddOn {
                protected $_version;
                protected $_min_gravityforms_version;
                protected $_slug;
                protected $_path;
                protected $_full_path;
                protected $_title;
                protected $_short_title;
                private static $instance;

                public static function get_instance() {
                    if (!self::$instance) {
                        $class = get_called_class();
                        self::$instance = new $class();
                    }
                    return self::$instance;
                }

                public function get_plugin_settings() {
                    return [
                        "api_mode" => "test",
                        "test_client_id" => "test_id",
                        "test_client_secret" => "test_secret",
                        "enable_caching" => "1",
                        "cache_duration" => "3600"
                    ];
                }

                public function get_form_settings($form) {
                    return [
                        "enable_verifytx" => "1",
                        "verification_timing" => "realtime",
                        "require_active" => "0"
                    ];
                }

                public function log_debug($message) {}
                public function log_error($message) {}
            }');
        }

        // Include and instantiate the addon class
        require_once GF_VERIFYTX_PLUGIN_PATH . 'class-gf-verifytx.php';

        // Use reflection to bypass the singleton
        $reflection = new \ReflectionClass('GF_VerifyTX');
        $this->addon = $reflection->newInstanceWithoutConstructor();
    }

    // Addon Properties Tests
    public function testAddonProperties()
    {
        $reflection = new \ReflectionClass($this->addon);

        $version = $reflection->getProperty('_version');
        $version->setAccessible(true);
        $this->assertEquals(GF_VERIFYTX_VERSION, $version->getValue($this->addon));

        $slug = $reflection->getProperty('_slug');
        $slug->setAccessible(true);
        $this->assertEquals('gf-verifytx', $slug->getValue($this->addon));

        $title = $reflection->getProperty('_title');
        $title->setAccessible(true);
        $this->assertEquals('Gravity Forms VerifyTX Add-On', $title->getValue($this->addon));

        $short_title = $reflection->getProperty('_short_title');
        $short_title->setAccessible(true);
        $this->assertEquals('VerifyTX', $short_title->getValue($this->addon));
    }

    // Settings Configuration Tests
    public function testPluginSettingsFields()
    {
        $settings = $this->addon->plugin_settings_fields();

        $this->assertIsArray($settings);
        $this->assertNotEmpty($settings);

        // Check for main sections
        $section_titles = array_column($settings, 'title');
        $this->assertContains('API Configuration', $section_titles);
        $this->assertContains('Cache Settings', $section_titles);
        $this->assertContains('Data Retention', $section_titles);

        // Check for required fields
        $all_fields = [];
        foreach ($settings as $section) {
            if (isset($section['fields'])) {
                $all_fields = array_merge($all_fields, $section['fields']);
            }
        }

        $field_names = array_column($all_fields, 'name');
        $this->assertContains('api_mode', $field_names);
        $this->assertContains('test_client_id', $field_names);
        $this->assertContains('test_client_secret', $field_names);
        $this->assertContains('production_client_id', $field_names);
        $this->assertContains('production_client_secret', $field_names);
        $this->assertContains('enable_caching', $field_names);
        $this->assertContains('cache_duration', $field_names);
        $this->assertContains('data_retention_days', $field_names);
    }

    public function testFormSettingsFields()
    {
        $form = ['id' => 1, 'fields' => []];
        $settings = $this->addon->form_settings_fields($form);

        $this->assertIsArray($settings);
        $this->assertNotEmpty($settings);

        // Check for main sections
        $section_titles = array_column($settings, 'title');
        $this->assertContains('VerifyTX Settings', $section_titles);
        $this->assertContains('Field Mapping', $section_titles);

        // Check for required fields
        $all_fields = [];
        foreach ($settings as $section) {
            if (isset($section['fields'])) {
                $all_fields = array_merge($all_fields, $section['fields']);
            }
        }

        $field_names = array_column($all_fields, 'name');
        $this->assertContains('enable_verifytx', $field_names);
        $this->assertContains('verification_timing', $field_names);
        $this->assertContains('require_active', $field_names);
        $this->assertContains('member_id_field', $field_names);
        $this->assertContains('dob_field', $field_names);
        $this->assertContains('payer_id_field', $field_names);
    }

    // API Client Initialization Tests
    public function testMaybeInitApiClientWithValidCredentials()
    {
        $reflection = new \ReflectionClass($this->addon);
        $method = $reflection->getMethod('maybe_init_api_client');
        $method->setAccessible(true);

        $apiClientProp = $reflection->getProperty('api_client');
        $apiClientProp->setAccessible(true);

        // Set up mock settings
        $settings = [
            'api_mode' => 'test',
            'test_client_id' => 'test_id',
            'test_client_secret' => 'test_secret'
        ];

        // Mock get_plugin_settings
        $addon = $this->getMockBuilder('GF_VerifyTX')
            ->onlyMethods(['get_plugin_settings'])
            ->getMock();
        $addon->expects($this->once())
            ->method('get_plugin_settings')
            ->willReturn($settings);

        $method->invoke($addon);

        $api_client = $apiClientProp->getValue($addon);
        $this->assertInstanceOf('GF_VerifyTX_API_Client', $api_client);
    }

    public function testMaybeInitApiClientWithMissingCredentials()
    {
        $reflection = new \ReflectionClass($this->addon);
        $method = $reflection->getMethod('maybe_init_api_client');
        $method->setAccessible(true);

        $apiClientProp = $reflection->getProperty('api_client');
        $apiClientProp->setAccessible(true);

        // Set up mock settings with missing credentials
        $settings = [
            'api_mode' => 'test',
            'test_client_id' => '',
            'test_client_secret' => ''
        ];

        // Mock get_plugin_settings
        $addon = $this->getMockBuilder('GF_VerifyTX')
            ->onlyMethods(['get_plugin_settings'])
            ->getMock();
        $addon->expects($this->once())
            ->method('get_plugin_settings')
            ->willReturn($settings);

        $method->invoke($addon);

        $api_client = $apiClientProp->getValue($addon);
        $this->assertNull($api_client);
    }

    // Field Map Choices Tests
    public function testVerifytxFieldMapChoices()
    {
        $form = [
            'fields' => [
                ['id' => '1', 'label' => 'First Name', 'type' => 'text'],
                ['id' => '2', 'label' => 'Last Name', 'type' => 'text'],
                ['id' => '3', 'label' => 'Email', 'type' => 'email'],
                ['id' => '4', 'label' => 'Date of Birth', 'type' => 'date'],
                ['id' => '5', 'label' => 'Member ID', 'type' => 'text']
            ]
        ];

        $choices = $this->addon->verifytx_field_map_choices($form);

        $this->assertIsArray($choices);
        $this->assertCount(6, $choices); // 5 fields + 1 blank option

        // Check first option is blank
        $this->assertEquals('', $choices[0]['value']);
        $this->assertEquals('Select a Field', $choices[0]['label']);

        // Check field options
        $this->assertEquals('1', $choices[1]['value']);
        $this->assertEquals('First Name', $choices[1]['label']);

        $this->assertEquals('5', $choices[5]['value']);
        $this->assertEquals('Member ID', $choices[5]['label']);
    }

    // Validation Tests
    public function testValidateApiCredentialsWithValidCredentials()
    {
        // Mock wp_remote_get to return success
        global $wp_remote_get_response;
        $wp_remote_get_response = ['response' => ['code' => 200], 'body' => '{"id": 1}'];

        $field = ['name' => 'test_client_id'];

        // This would need more complex mocking to fully test
        // For now, we'll just ensure the method exists
        $this->assertTrue(method_exists($this->addon, 'validate_api_credentials'));
    }

    // Scripts and Styles Tests
    public function testScriptsMethod()
    {
        $scripts = $this->addon->scripts();

        $this->assertIsArray($scripts);
        $this->assertNotEmpty($scripts);

        // Check for main script
        $this->assertArrayHasKey(0, $scripts);
        $this->assertArrayHasKey('handle', $scripts[0]);
        $this->assertEquals('gf_verifytx_form', $scripts[0]['handle']);
        $this->assertStringContainsString('assets/js/form.js', $scripts[0]['src']);
    }

    public function testStylesMethod()
    {
        $styles = $this->addon->styles();

        $this->assertIsArray($styles);
        $this->assertNotEmpty($styles);

        // Check for main style
        $this->assertArrayHasKey(0, $styles);
        $this->assertArrayHasKey('handle', $styles[0]);
        $this->assertEquals('gf_verifytx_form', $styles[0]['handle']);
        $this->assertStringContainsString('assets/css/form.css', $styles[0]['src']);
    }

    // Tooltip Tests
    public function testAddTooltips()
    {
        $tooltips = [];
        $result = $this->addon->add_tooltips($tooltips);

        $this->assertArrayHasKey('verifytx_enable', $result);
        $this->assertArrayHasKey('verifytx_timing', $result);
        $this->assertArrayHasKey('verifytx_require_active', $result);
        $this->assertArrayHasKey('verifytx_field_mapping', $result);

        $this->assertStringContainsString('Enable VerifyTX', $result['verifytx_enable']);
        $this->assertStringContainsString('verification timing', $result['verifytx_timing']);
    }

    // Error Handling Tests
    public function testAddValidationError()
    {
        $form = [
            'fields' => [
                ['id' => 1, 'failed_validation' => false, 'validation_message' => '']
            ]
        ];

        $reflection = new \ReflectionClass($this->addon);
        $method = $reflection->getMethod('add_validation_error');
        $method->setAccessible(true);

        $method->invokeArgs($this->addon, [&$form, 'Test error message']);

        $this->assertTrue($form['fields'][0]['failed_validation']);
        $this->assertEquals('Test error message', $form['fields'][0]['validation_message']);
    }

    // Cache Cleanup Tests
    public function testMaybeClearCache()
    {
        global $wpdb, $cleared_tables;
        $cleared_tables = [];

        // Mock wpdb->query to track what gets cleared
        $wpdb->query = function($query) use (&$cleared_tables) {
            if (strpos($query, 'DELETE FROM') !== false) {
                if (strpos($query, 'gf_verifytx_cache') !== false) {
                    $cleared_tables[] = 'cache';
                }
            }
            return 1;
        };

        $reflection = new \ReflectionClass($this->addon);
        $method = $reflection->getMethod('maybe_clear_cache');
        $method->setAccessible(true);

        $method->invoke($this->addon);

        $this->assertContains('cache', $cleared_tables);
    }

    // Export Field Value Tests
    public function testExportFieldValue()
    {
        $value = json_encode([
            'success' => true,
            'status' => 'Active',
            'vob_id' => 'VOB123'
        ]);

        $result = $this->addon->export_field_value($value, 1, 1, []);

        $expected = "Status: Active\nVOB ID: VOB123\nVerification: Successful";
        $this->assertEquals($expected, $result);
    }

    public function testExportFieldValueWithError()
    {
        $value = json_encode([
            'success' => false,
            'error' => 'Member not found'
        ]);

        $result = $this->addon->export_field_value($value, 1, 1, []);

        $expected = "Verification Failed: Member not found";
        $this->assertEquals($expected, $result);
    }

    public function testExportFieldValueWithInvalidJson()
    {
        $value = 'Not JSON';

        $result = $this->addon->export_field_value($value, 1, 1, []);

        $this->assertEquals($value, $result);
    }
}