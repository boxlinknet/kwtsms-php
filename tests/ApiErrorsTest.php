<?php

namespace KwtSMS\Tests;

use KwtSMS\ApiErrors;
use KwtSMS\KwtSMS;
use PHPUnit\Framework\TestCase;

/**
 * Mocks the remote API post method to simulate gateway behavior and HTTP codes.
 */
class MockKwtSMS extends KwtSMS
{
    /** @var array<string, mixed> */
    public $mockResponse = [];

    /**
     * Override API post request behavior
     * @param string $endpoint
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function post(string $endpoint, array $payload): array
    {
        return ApiErrors::enrichError($this->mockResponse);
    }
}

class ApiErrorsTest extends TestCase
{
    /** @var MockKwtSMS */
    private $client;

    protected function setUp(): void
    {
        $this->client = new MockKwtSMS('dummy_username', 'dummy_password', 'TEST', true);
    }

    public function testAuthenticationError(): void
    {
        // Injecting an ERR003 response globally
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR003',
            'description' => 'Wrong API username or password.'
        ];

        // Ensure Verify parses auth error properly
        [$ok, $bal, $err] = $this->client->verify();

        $this->assertFalse($ok);
        $this->assertSame(0.0, $bal);
        $this->assertStringContainsString('These are your API credentials', $err);

        // Send handles credentials correctly
        $result = $this->client->send('96598765432', 'Hello Password Mismatch');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR003', $result['code']);
        $this->assertStringContainsString('These are your API credentials', $result['action']);
    }

    public function testCountryNotCovered(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR026'
        ];

        $result = $this->client->send('96598765432', 'Testing out of bounds');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR026', $result['code']);
        $this->assertStringContainsString('enable the destination country', $result['action']);
    }

    public function testInvalidNumberReturned(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR025'
        ];

        $result = $this->client->send('96598765432', 'Test Invalid');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR025', $result['code']);
        $this->assertStringContainsString('country code', $result['action']);
    }

    public function testZeroBalance(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR010'
        ];

        $result = $this->client->send('96598765432', 'Test Broke');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR010', $result['code']);
        $this->assertStringContainsString('Recharge credits at kwtsms.com', $result['action']);
    }

    public function testIpBlacklisted(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR024'
        ];

        $result = $this->client->send('96598765432', 'Test IP lockdown');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR024', $result['code']);
        $this->assertStringContainsString('disable IP lockdown', $result['action']);
    }

    public function testRateLimiting(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR028'
        ];

        $result = $this->client->send('96598765432', 'Test flood');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR028', $result['code']);
        $this->assertStringContainsString('Wait 15 seconds', $result['action']);
    }

    public function testSenderIdBanned(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR008'
        ];

        $result = $this->client->send('96598765432', 'Spammer alert');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR008', $result['code']);
        $this->assertStringContainsString('different sender ID registered', $result['action']);
    }

    public function testUnknownApiErrorCode(): void
    {
        $this->client->mockResponse = [
            'result' => 'ERROR',
            'code' => 'ERR999',
            'description' => 'The server is on fire' // Some unique API error we don't know
        ];

        $result = $this->client->send('96598765432', 'Hello API Future');

        // Doesn't explode. Bubble up the code gracefully
        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR999', $result['code']);
        $this->assertSame('The server is on fire', $result['description']);
        // No action required (we don't map unknowns per the PRD)
        $this->assertArrayNotHasKey('action', $result);
    }
}
