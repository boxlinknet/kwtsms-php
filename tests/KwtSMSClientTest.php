<?php

namespace KwtSMS\Tests;

use KwtSMS\ApiErrors;
use KwtSMS\KwtSMS;
use PHPUnit\Framework\TestCase;

/**
 * Tracks every post() call and returns queued mock responses.
 * Falls back to a generic OK response when the queue is exhausted.
 */
class TrackingMockKwtSMS extends KwtSMS
{
    /** @var array<int, array<string, mixed>> */
    public $calls = [];

    /** @var array<int, array<string, mixed>> */
    public $mockResponses = [];

    protected function post(string $endpoint, array $payload): array
    {
        $this->calls[] = ['endpoint' => $endpoint, 'payload' => $payload];

        if (!empty($this->mockResponses)) {
            return ApiErrors::enrichError(array_shift($this->mockResponses));
        }

        return ApiErrors::enrichError([
            'result' => 'OK',
            'msg-id' => 'DEFAULT_MSG_ID',
            'balance' => 100.0,
            'purchased' => 1000.0,
            'points-charged' => 1.0,
            'balance-after' => 99.0,
        ]);
    }
}

class KwtSMSClientTest extends TestCase
{
    /** @var TrackingMockKwtSMS */
    private $client;

    protected function setUp(): void
    {
        // Empty log_file disables logging so tests don't write files
        $this->client = new TrackingMockKwtSMS('php_username', 'php_password', 'TEST', true, '');
    }

    // -----------------------------------------------------------------------
    // validate()
    // -----------------------------------------------------------------------

    public function testValidateAllValid(): void
    {
        $result = $this->client->validate(['96598765432', '96598765433']);

        $this->assertSame(2, $result['ok']);
        $this->assertSame(0, $result['er']);
        $this->assertSame(2, $result['nr']);
        $this->assertEmpty($result['rejected']);
        $this->assertSame('', $result['error']);
        $this->assertCount(2, $result['_valid_list']);
    }

    public function testValidateAllInvalid(): void
    {
        $result = $this->client->validate(['abc', 'not-a-phone']);

        $this->assertSame(0, $result['ok']);
        $this->assertSame(2, $result['er']);
        $this->assertCount(2, $result['rejected']);
        $this->assertStringContainsString('2 invalid', $result['error']);
        $this->assertEmpty($result['_valid_list']);
    }

    public function testValidateMixed(): void
    {
        $result = $this->client->validate(['96598765432', 'bad@email.com', '123456789']);

        $this->assertSame(2, $result['ok']);
        $this->assertSame(1, $result['er']);
        $this->assertCount(1, $result['rejected']);
        $this->assertSame('bad@email.com', $result['rejected'][0]['number']);
    }

    public function testValidateCommaStringInput(): void
    {
        $result = $this->client->validate('96598765432,96598765433');

        $this->assertSame(2, $result['ok']);
        $this->assertSame(0, $result['er']);
    }

    public function testValidateRawResultsShape(): void
    {
        $result = $this->client->validate('96598765432');

        $this->assertArrayHasKey('raw', $result);
        $this->assertCount(1, $result['raw']);
        $this->assertArrayHasKey('phone', $result['raw'][0]);
        $this->assertArrayHasKey('valid', $result['raw'][0]);
        $this->assertArrayHasKey('normalized', $result['raw'][0]);
    }

    // -----------------------------------------------------------------------
    // send() edge cases
    // -----------------------------------------------------------------------

    public function testSendNoValidNumbersReturnsErr006(): void
    {
        $result = $this->client->send('abc', 'Test message');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR006', $result['code']);
        $this->assertArrayHasKey('invalid', $result);
        $this->assertCount(1, $result['invalid']);
    }

    public function testSendEmptyMessageReturnsErr009(): void
    {
        $result = $this->client->send('96598765432', '');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR009', $result['code']);
        // Truly empty input: description must NOT mention cleaning
        $this->assertStringNotContainsString('cleaning', (string) ($result['description'] ?? ''));
    }

    public function testSendEmojiOnlyMessageReturnsErr009WithCleaningDescription(): void
    {
        // Emoji-only message becomes empty after clean_message() — ERR009 fires locally,
        // no API call made, and description explains WHY it is empty.
        $result = $this->client->send('96598765432', '😀🔥');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR009', $result['code']);
        $this->assertStringContainsString('cleaning', (string) ($result['description'] ?? ''));
        // No API call should have been made
        $this->assertEmpty($this->client->calls);
    }

    public function testSendEmojiOnlyMessageOnBulkPathReturnsErr009(): void
    {
        // ERR009 must fire locally before any API call even on the >200 bulk path.
        $result = $this->client->send($this->makeNumbers(201), '🔥🎉');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR009', $result['code']);
        $this->assertStringContainsString('cleaning', (string) ($result['description'] ?? ''));
        // The bulk path must never be reached — zero API calls
        $this->assertEmpty($this->client->calls);
    }

    public function testSendHtmlOnlyMessageReturnsErr009(): void
    {
        $result = $this->client->send('96598765432', '<b></b>');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR009', $result['code']);
        $this->assertStringContainsString('cleaning', (string) ($result['description'] ?? ''));
    }

    public function testSendWithMixedNumbersReturnsInvalidInResponse(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'MSG001', 'points-charged' => 1.0, 'balance-after' => 99.0],
        ];

        $result = $this->client->send(['96598765432', 'invalid@email.com'], 'Hello');

        $this->assertSame('OK', $result['result']);
        $this->assertArrayHasKey('invalid', $result);
        $this->assertCount(1, $result['invalid']);
        $this->assertSame('invalid@email.com', $result['invalid'][0]['number']);
    }

    public function testSendAllValidNoInvalidKey(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'MSG001'],
        ];

        $result = $this->client->send('96598765432', 'Hello');

        $this->assertSame('OK', $result['result']);
        $this->assertArrayNotHasKey('invalid', $result);
    }

    // -----------------------------------------------------------------------
    // balance() and purchased()
    // -----------------------------------------------------------------------

    public function testBalanceReturnsFloat(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'balance' => 42.5, 'purchased' => 100.0],
        ];

        $balance = $this->client->balance();

        $this->assertSame(42.5, $balance);
    }

    public function testBalanceReturnsNullOnError(): void
    {
        $this->client->mockResponses = [
            ['result' => 'ERROR', 'code' => 'ERR003'],
        ];

        $this->assertNull($this->client->balance());
    }

    public function testPurchasedIsNullBeforeAnyCall(): void
    {
        $this->assertNull($this->client->purchased());
    }

    public function testPurchasedIsSetAfterBalance(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'balance' => 42.5, 'purchased' => 100.0],
        ];

        $this->client->balance();

        $this->assertSame(100.0, $this->client->purchased());
    }

    public function testPurchasedIsSetAfterVerify(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'balance' => 50.0, 'purchased' => 200.0],
        ];

        $this->client->verify();

        $this->assertSame(200.0, $this->client->purchased());
    }

    // -----------------------------------------------------------------------
    // senderids() normalization
    // -----------------------------------------------------------------------

    public function testSenderIdsNormalizesKey(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'senderid' => ['MYAPP', 'KWT-SMS']],
        ];

        $result = $this->client->senderids();

        $this->assertSame('OK', $result['result']);
        $this->assertArrayHasKey('senderids', $result);
        $this->assertSame(['MYAPP', 'KWT-SMS'], $result['senderids']);
    }

    public function testSenderIdsOnError(): void
    {
        $this->client->mockResponses = [
            ['result' => 'ERROR', 'code' => 'ERR003'],
        ];

        $result = $this->client->senderids();

        $this->assertSame('ERROR', $result['result']);
        $this->assertArrayNotHasKey('senderids', $result);
    }

    // -----------------------------------------------------------------------
    // coverage() normalization
    // -----------------------------------------------------------------------

    public function testCoverageNormalizesKey(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'list' => ['965', '966', '971']],
        ];

        $result = $this->client->coverage();

        $this->assertSame('OK', $result['result']);
        $this->assertArrayHasKey('prefixes', $result);
        $this->assertSame(['965', '966', '971'], $result['prefixes']);
    }

    public function testCoverageOnError(): void
    {
        $this->client->mockResponses = [
            ['result' => 'ERROR', 'code' => 'ERR003'],
        ];

        $result = $this->client->coverage();

        $this->assertSame('ERROR', $result['result']);
        $this->assertArrayNotHasKey('prefixes', $result);
    }

    // -----------------------------------------------------------------------
    // Bulk send (> 200 numbers)
    // -----------------------------------------------------------------------

    /**
     * Generate N distinct valid Kuwait numbers.
     *
     * @return array<int, string>
     */
    private function makeNumbers(int $count): array
    {
        $numbers = [];
        for ($i = 0; $i < $count; $i++) {
            $numbers[] = '96598' . str_pad((string) $i, 6, '0', STR_PAD_LEFT);
        }
        return $numbers;
    }

    public function testBulkSendChunksIntoTwoBatches(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'BATCH1', 'points-charged' => 200.0, 'balance-after' => 800.0],
            ['result' => 'OK', 'msg-id' => 'BATCH2', 'points-charged' => 1.0, 'balance-after' => 799.0],
        ];

        $result = $this->client->send($this->makeNumbers(201), 'Bulk test');

        $this->assertSame('OK', $result['result']);
        $this->assertSame(2, $result['batches']);
        $this->assertSame(201, $result['numbers']);
        $this->assertCount(2, $result['msg-ids']);
        $this->assertSame(201.0, $result['points-charged']);
        $this->assertSame(799.0, $result['balance-after']);

        // Verify exactly 2 'send' endpoint calls, no extra 'balance' pre-fetch
        $sendCalls = array_values(array_filter($this->client->calls, function ($c) {
            return $c['endpoint'] === 'send';
        }));
        $this->assertCount(2, $sendCalls);

        // First batch: 200 numbers
        $this->assertCount(200, explode(',', $sendCalls[0]['payload']['mobile']));
        // Second batch: 1 number
        $this->assertCount(1, explode(',', $sendCalls[1]['payload']['mobile']));
    }

    public function testBulkSendPartialResultWhenSomeBatchesFail(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'BATCH1', 'points-charged' => 200.0, 'balance-after' => 800.0],
            ['result' => 'ERROR', 'code' => 'ERR010', 'description' => 'Zero balance'],
        ];

        $result = $this->client->send($this->makeNumbers(201), 'Partial test');

        $this->assertSame('PARTIAL', $result['result']);
        $this->assertCount(1, $result['msg-ids']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(2, $result['batches']);
    }

    public function testBulkSendAllFailedBubblesFirstError(): void
    {
        $this->client->mockResponses = [
            ['result' => 'ERROR', 'code' => 'ERR010', 'description' => 'Zero balance'],
            ['result' => 'ERROR', 'code' => 'ERR010', 'description' => 'Zero balance'],
        ];

        $result = $this->client->send($this->makeNumbers(201), 'All fail test');

        $this->assertSame('ERROR', $result['result']);
        $this->assertSame('ERR010', $result['code']);
        $this->assertSame(2, $result['batches']);
        $this->assertCount(2, $result['errors']);
        // Action should be bubbled from ApiErrors map
        $this->assertStringContainsString('Recharge credits', $result['action']);
    }

    public function testBulkSendNoBalancePreFetchCall(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'B1', 'points-charged' => 200.0, 'balance-after' => 800.0],
            ['result' => 'OK', 'msg-id' => 'B2', 'points-charged' => 200.0, 'balance-after' => 600.0],
        ];

        $this->client->send($this->makeNumbers(401), 'No pre-fetch test');

        $balanceCalls = array_filter($this->client->calls, function ($c) {
            return $c['endpoint'] === 'balance';
        });
        $this->assertCount(0, $balanceCalls);
    }

    public function testBulkSendInvalidNumbersReportedInResult(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'B1', 'points-charged' => 200.0, 'balance-after' => 800.0],
            ['result' => 'OK', 'msg-id' => 'B2', 'points-charged' => 1.0, 'balance-after' => 799.0],
        ];

        $numbers = $this->makeNumbers(201);
        $numbers[] = 'bad_number';

        $result = $this->client->send($numbers, 'Mixed bulk');

        $this->assertSame('OK', $result['result']);
        $this->assertArrayHasKey('invalid', $result);
        $this->assertCount(1, $result['invalid']);
        $this->assertSame('bad_number', $result['invalid'][0]['number']);
    }

    // -----------------------------------------------------------------------
    // from_env()
    // -----------------------------------------------------------------------

    public function testFromEnvWithMissingFileDoesNotThrow(): void
    {
        $client = KwtSMS::from_env('/completely/nonexistent/path/.env');
        $this->assertInstanceOf(KwtSMS::class, $client);
    }

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvLoadsCredentialsFromFile(): void
    {
        $keys = ['KWTSMS_USERNAME', 'KWTSMS_PASSWORD', 'KWTSMS_SENDER_ID', 'KWTSMS_TEST_MODE', 'KWTSMS_LOG_FILE'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = getenv($key);
            putenv($key);
            unset($_ENV[$key]);
        }

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        file_put_contents($envFile, implode("\n", [
            'KWTSMS_USERNAME=fileuser',
            'KWTSMS_PASSWORD=filepass',
            'KWTSMS_SENDER_ID=FILESENDER',
            'KWTSMS_TEST_MODE=1',
            'KWTSMS_LOG_FILE=',
        ]));

        try {
            $client = KwtSMS::from_env($envFile);

            $ref = new \ReflectionClass($client);
            $get = function (string $name) use ($ref, $client) {
                $prop = $ref->getProperty($name);
                $prop->setAccessible(true);
                return $prop->getValue($client);
            };

            $this->assertSame('fileuser', $get('username'));
            $this->assertSame('filepass', $get('password'));
            $this->assertSame('FILESENDER', $get('sender_id'));
            $this->assertTrue($get('test_mode'));
        } finally {
            unlink($envFile);
            foreach ($saved as $key => $val) {
                if ($val !== false) {
                    putenv("{$key}={$val}");
                    $_ENV[$key] = $val;
                }
            }
        }
    }

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvStripsQuotesFromValues(): void
    {
        $savedUser = getenv('KWTSMS_USERNAME');
        $savedPass = getenv('KWTSMS_PASSWORD');
        putenv('KWTSMS_USERNAME');
        putenv('KWTSMS_PASSWORD');
        unset($_ENV['KWTSMS_USERNAME'], $_ENV['KWTSMS_PASSWORD']);

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        file_put_contents($envFile, "KWTSMS_USERNAME=\"quoted_user\"\nKWTSMS_PASSWORD='single_quoted'\n");

        try {
            $client = KwtSMS::from_env($envFile);

            $ref = new \ReflectionClass($client);
            $get = function (string $name) use ($ref, $client) {
                $prop = $ref->getProperty($name);
                $prop->setAccessible(true);
                return $prop->getValue($client);
            };

            $this->assertSame('quoted_user', $get('username'));
            $this->assertSame('single_quoted', $get('password'));
        } finally {
            unlink($envFile);
            if ($savedUser !== false) {
                putenv("KWTSMS_USERNAME={$savedUser}");
                $_ENV['KWTSMS_USERNAME'] = $savedUser;
            }
            if ($savedPass !== false) {
                putenv("KWTSMS_PASSWORD={$savedPass}");
                $_ENV['KWTSMS_PASSWORD'] = $savedPass;
            }
        }
    }

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvStripsInlineComments(): void
    {
        $savedUser = getenv('KWTSMS_USERNAME');
        putenv('KWTSMS_USERNAME');
        unset($_ENV['KWTSMS_USERNAME']);

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        file_put_contents($envFile, "KWTSMS_USERNAME=commentuser # this is a comment\n");

        try {
            $client = KwtSMS::from_env($envFile);

            $ref = new \ReflectionClass($client);
            $prop = $ref->getProperty('username');
            $prop->setAccessible(true);

            $this->assertSame('commentuser', $prop->getValue($client));
        } finally {
            unlink($envFile);
            if ($savedUser !== false) {
                putenv("KWTSMS_USERNAME={$savedUser}");
                $_ENV['KWTSMS_USERNAME'] = $savedUser;
            }
        }
    }

    // -----------------------------------------------------------------------
    // Constructor: log file path traversal guard
    // -----------------------------------------------------------------------

    private function getLogFile(KwtSMS $client): string
    {
        // $log_file is private on KwtSMS; reflect on the declaring class, not the subclass
        $prop = (new \ReflectionClass(KwtSMS::class))->getProperty('log_file');
        $prop->setAccessible(true);
        return (string) $prop->getValue($client);
    }

    public function testConstructorRejectsLogPathWithDotDot(): void
    {
        $client = new TrackingMockKwtSMS('u', 'p', 'S', false, '../../etc/kwtsms.log');
        $this->assertSame('', $this->getLogFile($client));
    }

    public function testConstructorAcceptsNormalLogPath(): void
    {
        $client = new TrackingMockKwtSMS('u', 'p', 'S', false, '/var/log/kwtsms.log');
        $this->assertSame('/var/log/kwtsms.log', $this->getLogFile($client));
    }

    public function testConstructorAcceptsRelativeLogPath(): void
    {
        $client = new TrackingMockKwtSMS('u', 'p', 'S', false, 'logs/kwtsms.log');
        $this->assertSame('logs/kwtsms.log', $this->getLogFile($client));
    }

    public function testConstructorStripsNewlinesFromCredentials(): void
    {
        // Newlines in credentials must be stripped to prevent env-injection attacks
        // if the value is later written to a .env file or a log entry.
        $client = new TrackingMockKwtSMS("php_username\n", "php_password\r\n", 'S', false, '');

        $ref = new \ReflectionClass(KwtSMS::class);

        $userProp = $ref->getProperty('username');
        $userProp->setAccessible(true);
        $this->assertSame('php_username', $userProp->getValue($client));

        $passProp = $ref->getProperty('password');
        $passProp->setAccessible(true);
        $this->assertSame('php_password', $passProp->getValue($client));
    }

    // -----------------------------------------------------------------------
    // verify() unit tests (previously only tested via live API)
    // -----------------------------------------------------------------------

    public function testVerifyReturnsSuccessTuple(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'balance' => 42.5, 'purchased' => 100.0],
        ];

        [$ok, $balance, $err] = $this->client->verify();

        $this->assertTrue($ok);
        $this->assertSame(42.5, $balance);
        $this->assertSame('', $err);
    }

    public function testVerifyReturnsFailureTuple(): void
    {
        $this->client->mockResponses = [
            ['result' => 'ERROR', 'code' => 'ERR003'],
        ];

        [$ok, $balance, $err] = $this->client->verify();

        $this->assertFalse($ok);
        $this->assertSame(0.0, $balance);
        $this->assertStringContainsString('API credentials', $err);
    }

    // -----------------------------------------------------------------------
    // send() boundary: exactly 200 numbers must use single-send path
    // -----------------------------------------------------------------------

    public function testSend200NumbersUsesSingleSendPath(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'MSG001'],
        ];

        $result = $this->client->send($this->makeNumbers(200), 'Boundary test');

        $this->assertSame('OK', $result['result']);

        $sendCalls = array_values(array_filter($this->client->calls, function ($c) {
            return $c['endpoint'] === 'send';
        }));
        $this->assertCount(1, $sendCalls);
        // Single send path: no 'batches' key
        $this->assertArrayNotHasKey('batches', $result);
    }

    public function testSend201NumbersUsesBulkPath(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'B1', 'points-charged' => 200.0, 'balance-after' => 800.0],
            ['result' => 'OK', 'msg-id' => 'B2', 'points-charged' => 1.0, 'balance-after' => 799.0],
        ];

        $result = $this->client->send($this->makeNumbers(201), 'Bulk boundary test');

        $this->assertSame('OK', $result['result']);
        $this->assertArrayHasKey('batches', $result);
        $this->assertSame(2, $result['batches']);
    }

    // -----------------------------------------------------------------------
    // Phone deduplication
    // -----------------------------------------------------------------------

    public function testSendDeduplicatesDuplicateNumbers(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'MSG001'],
        ];

        // Three entries: two are the same normalized number
        $result = $this->client->send(
            ['96598765432', '96598765432', '96598765433'],
            'Dedup test'
        );

        $this->assertSame('OK', $result['result']);

        $sendCalls = array_values(array_filter($this->client->calls, function ($c) {
            return $c['endpoint'] === 'send';
        }));
        $this->assertCount(1, $sendCalls);
        // Only 2 unique numbers in the payload, not 3
        $this->assertCount(2, explode(',', $sendCalls[0]['payload']['mobile']));
    }

    public function testSendDeduplicatesNormalizedEquivalents(): void
    {
        $this->client->mockResponses = [
            ['result' => 'OK', 'msg-id' => 'MSG001'],
        ];

        // +965 9876-5432 and 96598765432 normalize to the same number
        $result = $this->client->send(
            ['+965 9876-5432', '96598765432'],
            'Normalization dedup test'
        );

        $this->assertSame('OK', $result['result']);

        $sendCalls = array_values(array_filter($this->client->calls, function ($c) {
            return $c['endpoint'] === 'send';
        }));
        $this->assertCount(1, $sendCalls);
        $this->assertCount(1, explode(',', $sendCalls[0]['payload']['mobile']));
    }

    public function testValidateStillCountsDuplicatesForAuditing(): void
    {
        // validate() should NOT deduplicate - it reports every input entry
        $result = $this->client->validate(['96598765432', '96598765432']);

        $this->assertSame(2, $result['ok']);  // both entries are valid
        $this->assertCount(2, $result['_valid_list']);
    }

    // -----------------------------------------------------------------------
    // .env parser edge cases
    // -----------------------------------------------------------------------

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvHandlesTabBeforeComment(): void
    {
        $savedUser = getenv('KWTSMS_USERNAME');
        putenv('KWTSMS_USERNAME');
        unset($_ENV['KWTSMS_USERNAME']);

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        // Tab character between value and # comment
        file_put_contents($envFile, "KWTSMS_USERNAME=tabuser\t# tab comment\n");

        try {
            $client = KwtSMS::from_env($envFile);

            $prop = (new \ReflectionClass(KwtSMS::class))->getProperty('username');
            $prop->setAccessible(true);

            $this->assertSame('tabuser', $prop->getValue($client));
        } finally {
            unlink($envFile);
            if ($savedUser !== false) {
                putenv("KWTSMS_USERNAME={$savedUser}");
                $_ENV['KWTSMS_USERNAME'] = $savedUser;
            }
        }
    }

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvHandlesMismatchedLeadingQuote(): void
    {
        $savedUser = getenv('KWTSMS_USERNAME');
        putenv('KWTSMS_USERNAME');
        unset($_ENV['KWTSMS_USERNAME']);

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        // Opening quote with no closing quote: quote should be stripped, value used as-is
        file_put_contents($envFile, "KWTSMS_USERNAME=\"mismatch_user\n");

        try {
            $client = KwtSMS::from_env($envFile);

            $prop = (new \ReflectionClass(KwtSMS::class))->getProperty('username');
            $prop->setAccessible(true);

            $this->assertSame('mismatch_user', $prop->getValue($client));
        } finally {
            unlink($envFile);
            if ($savedUser !== false) {
                putenv("KWTSMS_USERNAME={$savedUser}");
                $_ENV['KWTSMS_USERNAME'] = $savedUser;
            }
        }
    }

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvSkipsLinesWithEmptyKey(): void
    {
        $savedSender = getenv('KWTSMS_SENDER_ID');
        putenv('KWTSMS_SENDER_ID');
        unset($_ENV['KWTSMS_SENDER_ID']);

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        // Line with empty key should be skipped entirely
        file_put_contents($envFile, "=ignored_value\nKWTSMS_SENDER_ID=VALIDKEY\n");

        try {
            KwtSMS::from_env($envFile);
            $this->assertSame('VALIDKEY', getenv('KWTSMS_SENDER_ID'));
        } finally {
            unlink($envFile);
            if ($savedSender !== false) {
                putenv("KWTSMS_SENDER_ID={$savedSender}");
                $_ENV['KWTSMS_SENDER_ID'] = $savedSender;
            }
        }
    }

    /**
     * @backupGlobals disabled
     */
    public function testFromEnvQuotedValuePreservesInternalHash(): void
    {
        $savedPass = getenv('KWTSMS_PASSWORD');
        putenv('KWTSMS_PASSWORD');
        unset($_ENV['KWTSMS_PASSWORD']);

        $envFile = tempnam(sys_get_temp_dir(), 'kwtsms_env_');
        // A quoted value containing # should NOT have it stripped
        file_put_contents($envFile, "KWTSMS_PASSWORD=\"p@ss#word\"\n");

        try {
            $client = KwtSMS::from_env($envFile);

            $prop = (new \ReflectionClass(KwtSMS::class))->getProperty('password');
            $prop->setAccessible(true);

            $this->assertSame('p@ss#word', $prop->getValue($client));
        } finally {
            unlink($envFile);
            if ($savedPass !== false) {
                putenv("KWTSMS_PASSWORD={$savedPass}");
                $_ENV['KWTSMS_PASSWORD'] = $savedPass;
            }
        }
    }
}
