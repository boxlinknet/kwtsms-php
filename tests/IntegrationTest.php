<?php

namespace KwtSMS\Tests;

use KwtSMS\KwtSMS;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    /** @var KwtSMS */
    private $client;

    protected function setUp(): void
    {
        // Integration tests run if credentials provided, automatically setting test_mode=True
        $this->client = KwtSMS::from_env();
    }

    private function isConfigured(): bool
    {
        return !empty(getenv('KWTSMS_USERNAME'));
    }

    public function testVerifyRequiresCredentials()
    {
        if (!$this->isConfigured()) {
            $this->markTestSkipped('No real credentials provided. Skipping live API validation.');
        }

        [$ok, $balance, $err] = $this->client->verify();

        $this->assertTrue($ok);
        $this->assertIsFloat($balance);
        $this->assertGreaterThanOrEqual(0.0, $balance);
        $this->assertSame('', $err);
    }

    public function testWrongCredentials()
    {
        $badClient = new KwtSMS('dummy_user', 'dummy_pass_9999991823', 'KWT-SMS', true);
        [$ok, $bal, $err] = $badClient->verify();

        $this->assertFalse($ok);
        $this->assertSame(0.0, $bal);
        $this->assertStringContainsString('These are your API credentials', $err);
    }

    public function testSendToValidKuwaitNumber()
    {
        if (!$this->isConfigured()) {
            $this->markTestSkipped('No real credentials provided.');
        }

        // Test mode is forced on via from_env/setup usually. Let's explicitly construct memory-first test mode here.
        $testClient = new KwtSMS(
            getenv('KWTSMS_USERNAME') ?: '',
            getenv('KWTSMS_PASSWORD') ?: '',
            'KWT-SMS',
            true // Hardcode true safety
        );

        $result = $testClient->send('96598765432', 'Integration Test Hello API');

        $this->assertSame('OK', $result['result'], 'Send request failed: ' . json_encode($result));
        $this->assertArrayHasKey('msg-id', $result);
    }

    public function testSendInvalidNumberFormat()
    {
        if (!$this->isConfigured()) {
            $this->markTestSkipped('No real credentials provided.');
        }

        $result = $this->client->send('abcd', 'Bad letters everywhere');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR006', $result['code']);

        // Ensure invalid rejected array
        $this->assertArrayHasKey('invalid', $result);
        $this->assertSame('abcd', $result['invalid'][0]['number']);
        $this->assertStringContainsString('no digits', $result['invalid'][0]['error']);
    }

    public function testSendMixedNumbers()
    {
        if (!$this->isConfigured()) {
            $this->markTestSkipped('No real credentials provided.');
        }

        // Explicitly in test mode
        $testClient = new KwtSMS(
            getenv('KWTSMS_USERNAME') ?: '',
            getenv('KWTSMS_PASSWORD') ?: '',
            'KWT-SMS',
            true // Hardcode true safety
        );

        $result = $testClient->send(['96598765432', 'admin@example.com', '123'], 'Testing partial success');

        $this->assertSame('OK', $result['result']);

        $this->assertArrayHasKey('invalid', $result);
        $this->assertCount(2, $result['invalid']);
        $this->assertSame('admin@example.com', $result['invalid'][0]['number']);
        $this->assertSame('123', $result['invalid'][1]['number']);
    }

    public function testSenderIdsList()
    {
        if (!$this->isConfigured()) {
            $this->markTestSkipped('No real credentials provided.');
        }

        $result = $this->client->senderids();
        $this->assertSame('OK', $result['result']);
        $this->assertIsArray($result['senderids']);
    }

    public function testCoverageList()
    {
        if (!$this->isConfigured()) {
            $this->markTestSkipped('No real credentials provided.');
        }

        $result = $this->client->coverage();
        $this->assertSame('OK', $result['result']);
        $this->assertIsArray($result['prefixes']);
    }
}
