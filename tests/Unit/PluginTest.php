<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function testPluginFilesExist()
    {
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'gf-verifytx-addon.php');
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'class-gf-verifytx.php');
        $this->assertFileExists(GF_VERIFYTX_PLUGIN_PATH . 'includes/class-api-client.php');
    }

    public function testConstantsAreDefined()
    {
        $this->assertTrue(defined('GF_VERIFYTX_PLUGIN_PATH'));
        $this->assertTrue(defined('GF_VERIFYTX_VERSION'));
    }

    public function testWordPressFunctionsExist()
    {
        $this->assertTrue(function_exists('__'));
        $this->assertTrue(function_exists('esc_html'));
        $this->assertTrue(function_exists('wp_json_encode'));
    }
}