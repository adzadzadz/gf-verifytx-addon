<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;
use GF_VerifyTX_API_Client;

class ApiClientTest extends TestCase
{
    public function testClientCanBeInstantiated()
    {
        $client = new GF_VerifyTX_API_Client('test_id', 'test_secret', true);
        $this->assertInstanceOf(GF_VerifyTX_API_Client::class, $client);
    }

    public function testFormatDateReturnsCorrectFormat()
    {
        $client = new GF_VerifyTX_API_Client('test_id', 'test_secret', true);
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('format_date');
        $method->setAccessible(true);

        $result = $method->invoke($client, '2024-01-15');
        $this->assertEquals('01/15/2024', $result);
    }

    public function testFormatPhoneRemovesNonDigits()
    {
        $client = new GF_VerifyTX_API_Client('test_id', 'test_secret', true);
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('format_phone');
        $method->setAccessible(true);

        $result = $method->invoke($client, '(555) 123-4567');
        $this->assertEquals('5551234567', $result);
    }

    public function testMapRelationshipReturnsCorrectCode()
    {
        $client = new GF_VerifyTX_API_Client('test_id', 'test_secret', true);
        $reflection = new \ReflectionClass($client);
        $method = $reflection->getMethod('map_relationship');
        $method->setAccessible(true);

        $this->assertEquals('SR01', $method->invoke($client, 'self'));
        $this->assertEquals('SR02', $method->invoke($client, 'spouse'));
        $this->assertEquals('SR03', $method->invoke($client, 'child'));
        $this->assertEquals('SR01', $method->invoke($client, 'unknown'));
    }
}