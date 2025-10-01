<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;
use GF_VerifyTX_Verification;
use GF_VerifyTX_API_Client;

class VerificationComprehensiveTest extends TestCase
{
    private $apiClient;
    private $verification;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GF_VerifyTX_API_Client::class);
        $this->verification = new GF_VerifyTX_Verification($this->apiClient);
    }

    // Data Validation Tests
    public function testValidateDataRejectsEmptyMemberId()
    {
        $data = [
            'member_id' => '',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_member_id', $result->get_error_code());
    }

    public function testValidateDataRejectsEmptyDateOfBirth()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '',
            'payer_id' => 'BCBS'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_dob', $result->get_error_code());
    }

    public function testValidateDataRejectsEmptyPayerId()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => ''
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_payer', $result->get_error_code());
    }

    public function testValidateDataRejectsInvalidEmail()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS',
            'email' => 'not-an-email'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_email', $result->get_error_code());
    }

    public function testValidateDataRejectsShortPhone()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS',
            'phone' => '123'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('invalid_phone', $result->get_error_code());
    }

    public function testValidateDataAcceptsValidData()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS',
            'email' => 'test@example.com',
            'phone' => '555-123-4567'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertIsArray($result);
        $this->assertEquals($data, $result);
    }

    // Verification Processing Tests
    public function testVerifyInsuranceWithValidData()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $apiResponse = [
            'success' => true,
            'status' => 'Active',
            'vob_id' => 'VOB123',
            'benefits' => []
        ];

        $this->apiClient->expects($this->once())
            ->method('create_and_verify_vob')
            ->with($data)
            ->willReturn($apiResponse);

        $result = $this->verification->verify_insurance($data, 1, 1);

        $this->assertTrue($result['success']);
        $this->assertEquals('Active', $result['status']);
        $this->assertEquals('VOB123', $result['vob_id']);
    }

    public function testVerifyInsuranceWithApiError()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $this->apiClient->expects($this->once())
            ->method('create_and_verify_vob')
            ->with($data)
            ->willReturn(new \WP_Error('api_error', 'API request failed'));

        $result = $this->verification->verify_insurance($data, 1, 1);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('api_error', $result->get_error_code());
    }

    public function testVerifyInsuranceWithInvalidData()
    {
        $data = [
            'member_id' => '',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $result = $this->verification->verify_insurance($data, 1, 1);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('missing_member_id', $result->get_error_code());
    }

    // Display Formatting Tests
    public function testFormatForDisplayHandlesSuccessResult()
    {
        $result = [
            'success' => true,
            'status' => 'Active',
            'benefits' => [
                'deductible' => ['individual' => 1000],
                'out_of_pocket_max' => ['individual' => 5000]
            ],
            'coverage_details' => [
                'plan_name' => 'Gold Plan',
                'group_number' => 'GRP123'
            ]
        ];

        $formatted = $this->verification->format_for_display($result);

        $this->assertTrue($formatted['success']);
        $this->assertEquals('Active', $formatted['status']);
        $this->assertEquals('Insurance verified successfully', $formatted['message']);
        $this->assertArrayHasKey('html', $formatted);
        $this->assertArrayHasKey('text', $formatted);
        $this->assertStringContainsString('Gold Plan', $formatted['html']);
    }

    public function testFormatForDisplayHandlesFailureResult()
    {
        $result = [
            'success' => false,
            'status' => 'error',
            'error' => 'Member not found'
        ];

        $formatted = $this->verification->format_for_display($result);

        $this->assertFalse($formatted['success']);
        $this->assertEquals('error', $formatted['status']);
        $this->assertEquals('Member not found', $formatted['message']);
        $this->assertArrayHasKey('html', $formatted);
        $this->assertArrayHasKey('text', $formatted);
    }

    public function testFormatForDisplayHandlesInactiveStatus()
    {
        $result = [
            'success' => true,
            'status' => 'Inactive',
            'termination_date' => '2023-12-31'
        ];

        $formatted = $this->verification->format_for_display($result);

        $this->assertTrue($formatted['success']);
        $this->assertEquals('Inactive', $formatted['status']);
        $this->assertStringContainsString('inactive', $formatted['message']);
        $this->assertStringContainsString('2023-12-31', $formatted['html']);
    }

    // Cache Key Generation Tests
    public function testGenerateCacheKeyConsistency()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('generate_cache_key');
        $method->setAccessible(true);

        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $key1 = $method->invoke($this->verification, $data);
        $key2 = $method->invoke($this->verification, $data);

        $this->assertEquals($key1, $key2);
    }

    public function testGenerateCacheKeyDifferentForDifferentData()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('generate_cache_key');
        $method->setAccessible(true);

        $data1 = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $data2 = [
            'member_id' => 'MEM456',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $key1 = $method->invoke($this->verification, $data1);
        $key2 = $method->invoke($this->verification, $data2);

        $this->assertNotEquals($key1, $key2);
    }

    // Benefits Formatting Tests
    public function testFormatCoverageSummaryWithFullBenefits()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('format_coverage_summary');
        $method->setAccessible(true);

        $benefits = [
            'in_network' => [
                'deductible' => [
                    'individual' => 1000,
                    'family' => 2000,
                    'met' => 500
                ],
                'out_of_pocket_max' => [
                    'individual' => 5000,
                    'family' => 10000,
                    'met' => 1000
                ],
                'coinsurance' => 80,
                'copay' => [
                    'primary_care' => 25,
                    'specialist' => 50,
                    'emergency' => 250
                ]
            ],
            'out_of_network' => [
                'deductible' => [
                    'individual' => 2000,
                    'family' => 4000
                ],
                'coinsurance' => 60
            ]
        ];

        $result = $method->invoke($this->verification, $benefits);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('in_network', $result);
        $this->assertArrayHasKey('out_of_network', $result);
        $this->assertArrayHasKey('deductible', $result['in_network']);
        $this->assertEquals('$1,000 individual / $2,000 family ($500 met)', $result['in_network']['deductible']);
        $this->assertEquals('80%', $result['in_network']['coinsurance']);
    }

    public function testFormatCoverageSummaryWithEmptyBenefits()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('format_coverage_summary');
        $method->setAccessible(true);

        $benefits = [];

        $result = $method->invoke($this->verification, $benefits);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // Response Processing Tests
    public function testProcessVerificationResponseSuccess()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('process_verification_response');
        $method->setAccessible(true);

        $response = [
            'success' => true,
            'status' => 'Active',
            'vob_id' => 'VOB123',
            'benefits' => [],
            'coverage_details' => []
        ];

        $result = $method->invoke($this->verification, $response);

        $this->assertTrue($result['success']);
        $this->assertEquals('Active', $result['status']);
        $this->assertEquals('VOB123', $result['vob_id']);
    }

    public function testProcessVerificationResponseHandlesUnexpectedFormat()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('process_verification_response');
        $method->setAccessible(true);

        $response = 'unexpected string response';

        $result = $method->invoke($this->verification, $response);

        $this->assertFalse($result['success']);
        $this->assertEquals('error', $result['status']);
        $this->assertStringContainsString('Invalid response format', $result['error']);
    }

    // HTML Generation Tests
    public function testGenerateResultHtmlForSuccess()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('generate_result_html');
        $method->setAccessible(true);

        $result = [
            'success' => true,
            'status' => 'Active',
            'vob_id' => 'VOB123',
            'coverage_details' => [
                'plan_name' => 'Gold Plan',
                'group_number' => 'GRP123'
            ],
            'benefits' => []
        ];

        $html = $method->invoke($this->verification, $result);

        $this->assertStringContainsString('verification-success', $html);
        $this->assertStringContainsString('Active', $html);
        $this->assertStringContainsString('Gold Plan', $html);
        $this->assertStringContainsString('GRP123', $html);
    }

    public function testGenerateResultHtmlForError()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('generate_result_html');
        $method->setAccessible(true);

        $result = [
            'success' => false,
            'status' => 'error',
            'error' => 'API request failed'
        ];

        $html = $method->invoke($this->verification, $result);

        $this->assertStringContainsString('verification-error', $html);
        $this->assertStringContainsString('API request failed', $html);
    }

    // Text Generation Tests
    public function testGenerateResultTextForSuccess()
    {
        $reflection = new \ReflectionClass($this->verification);
        $method = $reflection->getMethod('generate_result_text');
        $method->setAccessible(true);

        $result = [
            'success' => true,
            'status' => 'Active',
            'vob_id' => 'VOB123',
            'coverage_details' => [
                'plan_name' => 'Gold Plan',
                'group_number' => 'GRP123'
            ]
        ];

        $text = $method->invoke($this->verification, $result);

        $this->assertStringContainsString('Status: Active', $text);
        $this->assertStringContainsString('Plan: Gold Plan', $text);
        $this->assertStringContainsString('Group: GRP123', $text);
    }
}