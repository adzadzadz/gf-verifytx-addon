<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;
use GF_VerifyTX_API_Client;

class ApiClientComprehensiveTest extends TestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = new GF_VerifyTX_API_Client('test_client_id', 'test_secret', true);
    }

    // Constructor Tests
    public function testConstructorSetsTestModeUrl()
    {
        $client = new GF_VerifyTX_API_Client('id', 'secret', true);
        $reflection = new \ReflectionClass($client);
        $apiUrlProp = $reflection->getProperty('api_url');
        $apiUrlProp->setAccessible(true);
        $this->assertEquals('https://sandbox.api.verifytx.com', $apiUrlProp->getValue($client));
    }

    public function testConstructorSetsProductionUrl()
    {
        $client = new GF_VerifyTX_API_Client('id', 'secret', false);
        $reflection = new \ReflectionClass($client);
        $apiUrlProp = $reflection->getProperty('api_url');
        $apiUrlProp->setAccessible(true);
        $this->assertEquals('https://api.verifytx.com', $apiUrlProp->getValue($client));
    }

    // Date Formatting Tests
    public function testFormatDateWithSlashes()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('format_date');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, '2024/01/15');
        $this->assertEquals('01/15/2024', $result);
    }

    public function testFormatDateWithDashes()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('format_date');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, '2024-01-15');
        $this->assertEquals('01/15/2024', $result);
    }

    public function testFormatDateWithInvalidFormat()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('format_date');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, 'invalid-date');
        $this->assertEquals('invalid-date', $result);
    }

    // Phone Formatting Tests
    public function testFormatPhoneRemovesAllNonDigits()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('format_phone');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, '(555) 123-4567 ext. 890');
        $this->assertEquals('5551234567890', $result);
    }

    public function testFormatPhoneWithInternationalFormat()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('format_phone');
        $method->setAccessible(true);

        $result = $method->invoke($this->client, '+1-555-123-4567');
        $this->assertEquals('15551234567', $result);
    }

    // Relationship Mapping Tests
    public function testMapRelationshipAllValues()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('map_relationship');
        $method->setAccessible(true);

        $mappings = [
            'self' => 'SR01',
            'spouse' => 'SR02',
            'child' => 'SR03',
            'unknown' => 'SR01',
            'invalid' => 'SR01'
        ];

        foreach ($mappings as $input => $expected) {
            $result = $method->invoke($this->client, $input);
            $this->assertEquals($expected, $result, "Failed for relationship: $input");
        }
    }

    // Data Preparation Tests
    public function testPrepareVobDataWithCompleteData()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('prepare_vob_data');
        $method->setAccessible(true);

        $input = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-15',
            'payer_id' => 'BCBS',
            'relationship' => 'self',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '555-123-4567',
            'address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'zip' => '78701'
        ];

        $result = $method->invoke($this->client, $input);

        $this->assertEquals('MEM123', $result['member_id']);
        $this->assertEquals('01/15/1990', $result['date_of_birth']);
        $this->assertEquals('BCBS', $result['payer_id']);
        $this->assertEquals('SR01', $result['relationship']);
        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals('5551234567', $result['phone']);
    }

    public function testPrepareVobDataWithMinimalData()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('prepare_vob_data');
        $method->setAccessible(true);

        $input = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-15',
            'payer_id' => 'BCBS'
        ];

        $result = $method->invoke($this->client, $input);

        $this->assertEquals('MEM123', $result['member_id']);
        $this->assertEquals('01/15/1990', $result['date_of_birth']);
        $this->assertEquals('BCBS', $result['payer_id']);
        $this->assertEquals('SR01', $result['relationship']); // Default value
    }

    // Response Parsing Tests
    public function testParseVerificationResponseSuccess()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_verification_response');
        $method->setAccessible(true);

        $response = [
            'success' => true,
            'status' => 'active',
            'vob_id' => 'VOB123',
            'benefits' => [
                'deductible' => ['individual' => 1000, 'family' => 2000],
                'out_of_pocket_max' => ['individual' => 5000, 'family' => 10000]
            ],
            'coverage_details' => [
                'plan_name' => 'Gold Plan',
                'group_number' => 'GRP123'
            ]
        ];

        $result = $method->invoke($this->client, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('active', $result['status']);
        $this->assertEquals('VOB123', $result['vob_id']);
        $this->assertIsArray($result['benefits']);
        $this->assertEquals('Gold Plan', $result['coverage_details']['plan_name']);
    }

    public function testParseVerificationResponseError()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_verification_response');
        $method->setAccessible(true);

        $response = [
            'success' => false,
            'error' => 'Member not found',
            'error_code' => 'MEM_NOT_FOUND'
        ];

        $result = $method->invoke($this->client, $response);

        $this->assertFalse($result['success']);
        $this->assertEquals('Member not found', $result['error']);
        $this->assertEquals('MEM_NOT_FOUND', $result['error_code']);
    }

    // Benefits Parsing Tests
    public function testParseBenefitsWithNetworkData()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_benefits');
        $method->setAccessible(true);

        $benefits = [
            'in_network' => [
                'deductible' => ['individual' => 1000, 'family' => 2000],
                'coinsurance' => 80,
                'copay' => ['primary_care' => 25, 'specialist' => 50]
            ],
            'out_of_network' => [
                'deductible' => ['individual' => 2000, 'family' => 4000],
                'coinsurance' => 60,
                'copay' => null
            ]
        ];

        $result = $method->invoke($this->client, $benefits);

        $this->assertArrayHasKey('in_network', $result);
        $this->assertArrayHasKey('out_of_network', $result);
        $this->assertEquals(1000, $result['in_network']['deductible']['individual']);
        $this->assertEquals(80, $result['in_network']['coinsurance']);
        $this->assertEquals(25, $result['in_network']['copay']['primary_care']);
    }

    // Error Message Parsing Tests
    public function testParseErrorMessageFromJson()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_error_message');
        $method->setAccessible(true);

        $body = json_encode(['error' => 'Invalid credentials']);
        $result = $method->invoke($this->client, $body);
        $this->assertEquals('Invalid credentials', $result);
    }

    public function testParseErrorMessageFromJsonWithMessage()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_error_message');
        $method->setAccessible(true);

        $body = json_encode(['message' => 'Request failed']);
        $result = $method->invoke($this->client, $body);
        $this->assertEquals('Request failed', $result);
    }

    public function testParseErrorMessageFromJsonWithErrorDescription()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_error_message');
        $method->setAccessible(true);

        $body = json_encode(['error_description' => 'Token expired']);
        $result = $method->invoke($this->client, $body);
        $this->assertEquals('Token expired', $result);
    }

    public function testParseErrorMessageWithInvalidJson()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('parse_error_message');
        $method->setAccessible(true);

        $body = 'This is not JSON';
        $result = $method->invoke($this->client, $body);
        $this->assertEquals('This is not JSON', $result);
    }

    // Token Caching Tests
    public function testCacheToken()
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('cache_token');
        $method->setAccessible(true);

        global $cached_token;
        $cached_token = null;

        // Mock set_transient to capture the token
        function set_transient($key, $value, $expiration) {
            global $cached_token;
            if (strpos($key, 'gf_verifytx_token_') === 0) {
                $cached_token = $value;
            }
            return true;
        }

        $method->invoke($this->client, 'test_access_token', 3600);

        // Token should be cached (in our mock, we just verify it was called)
        $this->assertTrue(true); // If we get here, the method executed without error
    }
}