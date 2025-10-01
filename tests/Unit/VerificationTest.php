<?php

namespace GFVerifyTX\Tests\Unit;

use PHPUnit\Framework\TestCase;
use GF_VerifyTX_Verification;
use GF_VerifyTX_API_Client;

class VerificationTest extends TestCase
{
    private $apiClient;
    private $verification;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(GF_VerifyTX_API_Client::class);
        $this->verification = new GF_VerifyTX_Verification($this->apiClient);
    }

    public function testValidateDataRejectsEmptyMemberId()
    {
        $data = [
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    public function testValidateDataRejectsInvalidEmail()
    {
        $data = [
            'member_id' => 'MEM123',
            'date_of_birth' => '1990-01-01',
            'payer_id' => 'BCBS',
            'email' => 'invalid-email'
        ];

        $result = $this->verification->validate_data($data);
        $this->assertInstanceOf(\WP_Error::class, $result);
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

    public function testFormatForDisplayHandlesSuccessResult()
    {
        $result = [
            'success' => true,
            'status' => 'Active',
            'benefits' => []
        ];

        $formatted = $this->verification->format_for_display($result);

        $this->assertTrue($formatted['success']);
        $this->assertEquals('Active', $formatted['status']);
        $this->assertEquals('Insurance verified successfully', $formatted['message']);
        $this->assertArrayHasKey('html', $formatted);
        $this->assertArrayHasKey('text', $formatted);
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
    }
}