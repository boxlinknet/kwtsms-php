<?php

namespace KwtSMS;

class KwtSMS
{
    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var string */
    private $sender_id;

    /** @var bool */
    private $test_mode;

    /** @var string */
    private $log_file;

    /** @var float|null */
    private $purchased;

    /** @var string */
    private const API_BASE = 'https://www.kwtsms.com/API';

    /**
     * @param string $username
     * @param string $password
     * @param string $sender_id
     * @param bool $test_mode
     * @param string $log_file
     */
    public function __construct(
        string $username,
        string $password,
        string $sender_id = 'KWT-SMS',
        bool $test_mode = false,
        string $log_file = 'kwtsms.log'
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->sender_id = $sender_id;
        $this->test_mode = $test_mode;
        $this->log_file = $log_file;
        $this->purchased = null;
    }

    /**
     * Factory: Load credentials from env vars -> .env fallback.
     * 
     * @param string $env_file
     * @return self
     */
    public static function from_env(string $env_file = '.env'): self
    {
        // Simple manual parsing to avoid external dependencies like Dotenv
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) {
                        continue;
                    }
                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);
                        if (!getenv($key)) {
                            putenv("{$key}={$val}");
                            $_ENV[$key] = $val;
                        }
                    }
                }
            }
        }

        $username = getenv('KWTSMS_USERNAME') ?: ($_ENV['KWTSMS_USERNAME'] ?? '');
        $password = getenv('KWTSMS_PASSWORD') ?: ($_ENV['KWTSMS_PASSWORD'] ?? '');
        $sender = getenv('KWTSMS_SENDER_ID') ?: ($_ENV['KWTSMS_SENDER_ID'] ?? 'KWT-SMS');
        $test_mode = (bool) (getenv('KWTSMS_TEST_MODE') ?: ($_ENV['KWTSMS_TEST_MODE'] ?? false));
        $log_file = getenv('KWTSMS_LOG_FILE') ?: ($_ENV['KWTSMS_LOG_FILE'] ?? 'kwtsms.log');

        return new self((string) $username, (string) $password, (string) $sender, $test_mode, (string) $log_file);
    }

    /**
     * @param string $endpoint
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function post(string $endpoint, array $payload): array
    {
        // Base inject credentials
        $payload['username'] = $this->username;
        $payload['password'] = $this->password;

        $json = json_encode($payload);
        if ($json === false) {
            return ApiErrors::enrichError(['result' => 'ERROR', 'code' => 'ERR999', 'description' => 'JSON encode error: ' . json_last_error_msg()]);
        }

        $url = self::API_BASE . '/' . trim($endpoint, '/') . '/';

        $ch = curl_init($url);
        if ($ch === false) {
            return ApiErrors::enrichError(['result' => 'ERROR', 'code' => 'ERR999', 'description' => 'Failed to initialize curl']);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        // Critical: Do NOT use CURLOPT_FAILONERROR because we *need* the 4xx/5xx body for API errors!

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $ok = false;
        $decoded = null;

        if ($responseBody === false || $responseBody === '') {
            $err = [
                'result' => 'ERROR',
                'code' => 'ERR999',
                'description' => $error ? 'Network error: ' . $error : 'Empty response from API'
            ];
            $this->log($endpoint, $payload, $err, false, $err['description']);
            return ApiErrors::enrichError($err);
        }

        // Parse JSON response. Note that some valid responses (like 403 blocks) return JSON errors.
        $decoded = json_decode((string) $responseBody, true);

        if (!is_array($decoded)) {
            $err = [
                'result' => 'ERROR',
                'code' => 'ERR999',
                'description' => 'Invalid JSON from API. HTTP Code: ' . $httpCode
            ];
            $this->log($endpoint, $payload, $err, false, 'Invalid JSON from API');
            return ApiErrors::enrichError($err);
        }

        // Even with HTTP 200, check the "result" property natively
        $ok = isset($decoded['result']) && $decoded['result'] === 'OK';

        $this->log($endpoint, $payload, $decoded, $ok, $ok ? null : ($decoded['description'] ?? 'API Error'));

        return ApiErrors::enrichError($decoded);
    }

    /**
     * @param string $endpoint
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $response
     * @param bool $ok
     * @param string|null $error
     * @return void
     */
    private function log(string $endpoint, array $payload, array $response, bool $ok, ?string $error = null): void
    {
        if (empty($this->log_file)) {
            return;
        }

        // Mask password
        $maskedPayload = $payload;
        if (isset($maskedPayload['password'])) {
            $maskedPayload['password'] = '***';
        }

        $entry = [
            'ts' => gmdate('Y-m-d\TH:i:s\Z'),
            'endpoint' => $endpoint,
            'request' => $maskedPayload,
            'response' => $response,
            'ok' => $ok,
            'error' => $error
        ];

        $jsonLine = json_encode($entry) . "\n";
        if ($jsonLine !== false) {
            // Error suppression is intentional; PRD explicitly states "Logging MUST never crash the main flow"
            @file_put_contents($this->log_file, $jsonLine, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * @return array{0: bool, 1: float, 2: string}
     */
    public function verify(): array
    {
        $response = $this->post('balance', []);

        if (isset($response['result']) && $response['result'] === 'OK') {
            $balance = (float) ($response['balance'] ?? 0);
            $this->purchased = (float) ($response['purchased'] ?? 0);
            return [true, $balance, ''];
        }

        return [false, 0.0, $response['action'] ?? ($response['description'] ?? 'Unknown error')];
    }

    /**
     * @return float|null
     */
    public function balance(): ?float
    {
        $response = $this->post('balance', []);

        if (isset($response['result']) && $response['result'] === 'OK') {
            $this->purchased = (float) ($response['purchased'] ?? 0);
            return (float) ($response['balance'] ?? 0);
        }

        return null; // Silent failure per standard usage, logs will capture it
    }

    /**
     * Extract the active sender ID.
     * @param string|null $sender
     * @return string
     */
    private function active_sender(?string $sender = null): string
    {
        return $sender ?: $this->sender_id;
    }

    /**
     * Clean and split a list of numbers. Returns array of raw parts to be validated.
     * @param string|array<int, string> $mobile
     * @return array<int, string>
     */
    private function prepare_numbers($mobile): array
    {
        if (is_array($mobile)) {
            return $mobile;
        }

        // Split by comma if string
        $parts = explode(',', $mobile);
        return array_map('trim', $parts);
    }

    /**
     * Bulk send logic handling >200 numbers, 0.5s delays, and retries.
     *
     * @param array<int, string> $valid
     * @param array<int, string> $invalid
     * @param array<int, mixed> $validation_results (optional) used only if returning raw validation info
     * @param string $message
     * @param string|null $sender
     * @return array<string, mixed>
     */
    private function _send_bulk(array $valid, array $invalid, array $validation_results, string $message, ?string $sender): array
    {
        $batches = array_chunk($valid, 200);

        $aggregated = [
            'result' => '',
            'code' => '',
            'description' => '',
            'action' => '',
            'batches' => count($batches),
            'msg-ids' => [],
            'numbers' => count($valid),
            'points-charged' => 0.0,
            'balance-after' => $this->balance(), // Pre-fetch balance if no successful sends update it
            'errors' => [],
            'invalid' => $invalid
        ];

        $hasError = false;
        $hasSuccess = false;

        foreach ($batches as $index => $batch) {
            if ($index > 0) {
                usleep(500000); // 0.5 sec delay between batches
            }

            $attempt = 1;
            $max_attempts = 4;
            $batchStr = implode(',', $batch);
            $response = null;

            while ($attempt <= $max_attempts) {
                $payload = [
                    'mobile' => $batchStr,
                    'message' => $message,
                    'sender' => $this->active_sender($sender),
                    'test' => $this->test_mode ? 1 : 0
                ];

                $response = $this->post('send', $payload);

                // Queue full (ERR013), and we haven't maxed out attempts
                if (isset($response['result']) && $response['result'] === 'ERROR' && isset($response['code']) && $response['code'] === 'ERR013' && $attempt < $max_attempts) {
                    $delay = ($attempt === 1) ? 30 : (($attempt === 2) ? 60 : 120);
                    $this->log('send_retry', ['batch' => $index, 'attempt' => $attempt, 'delay' => $delay], [], false, 'Queue full, retrying...');
                    sleep($delay);
                    $attempt++;
                    continue;
                }

                break; // Break retry loop on success or definitive error
            }

            if (isset($response['result']) && $response['result'] === 'OK') {
                $hasSuccess = true;
                if (isset($response['msg-id']))
                    $aggregated['msg-ids'][] = $response['msg-id'];
                if (isset($response['points-charged']))
                    $aggregated['points-charged'] += (float) $response['points-charged'];
                if (isset($response['balance-after']))
                    $aggregated['balance-after'] = (float) $response['balance-after'];
            } else {
                $hasError = true;
                $errEntry = [
                    'batch' => $index + 1,
                    'code' => $response['code'] ?? 'ERR999',
                    'description' => $response['description'] ?? 'Unknown Error'
                ];
                $aggregated['errors'][] = $errEntry;
            }
        }

        if ($hasSuccess && !$hasError) {
            $aggregated['result'] = 'OK';
            $aggregated['description'] = 'Bulk processed successfully';
        } elseif ($hasSuccess && $hasError) {
            $aggregated['result'] = 'PARTIAL';
            $aggregated['code'] = 'PARTIAL';
            $aggregated['description'] = 'Some batches failed';
            $aggregated['action'] = 'Check errors array for specific batch failures';
        } else {
            $aggregated['result'] = 'ERROR';
            $aggregated['code'] = 'BULK_ERROR';
            $aggregated['description'] = 'All batches failed';

            // If we have errors, grab the first one to bubble up
            if (count($aggregated['errors']) > 0) {
                $aggregated['code'] = $aggregated['errors'][0]['code'];
                $aggregated['description'] = $aggregated['errors'][0]['description'];

                // Bubbling action from api limits
                if (isset(ApiErrors::ERRORS[$aggregated['code']])) {
                    $aggregated['action'] = ApiErrors::ERRORS[$aggregated['code']]['action'];
                }
            }
        }

        // Return empty invalid array if none, ensuring predictable shape
        if (empty($aggregated['invalid'])) {
            unset($aggregated['invalid']);
        }

        return $aggregated;
    }

    /**
     * Validate numbers against PhoneUtils logic without calling API.
     * 
     * @param string|array<int, string> $mobile
     * @return array<string, mixed>
     */
    public function validate($mobile): array
    {
        $raw_numbers = $this->prepare_numbers($mobile);

        $ok = 0;
        $er = 0;
        $nr = count($raw_numbers);
        $rejected = [];
        $validation_results = [];
        $valid_list = [];

        foreach ($raw_numbers as $phone) {
            list($isValid, $errMsg, $normalized) = PhoneUtils::validate_phone_input($phone);

            $validation_results[] = [
                'phone' => $phone,
                'valid' => $isValid,
                'error' => $errMsg,
                'normalized' => $normalized
            ];

            if ($isValid) {
                $ok++;
                $valid_list[] = $normalized;
            } else {
                $er++;
                $rejected[] = [
                    'number' => $phone,
                    'error' => $errMsg
                ];
            }
        }

        return [
            'ok' => $ok,
            'er' => $er,
            'nr' => $nr,
            'rejected' => $rejected,
            'error' => $er > 0 ? "Found {$er} invalid numbers out of {$nr}" : '',
            'raw' => $validation_results,
            '_valid_list' => $valid_list, // Internal helper representation
        ];
    }

    /**
     * Core functionality to clean messages and dispatch SMS to gateway.
     *
     * @param string|array<int, string> $mobile
     * @param string $message
     * @param string|null $sender
     * @return array<string, mixed>
     */
    public function send($mobile, string $message, ?string $sender = null): array
    {
        $validation = $this->validate($mobile);
        $valid_list = $validation['_valid_list'];
        $rejected = $validation['rejected'];

        // Core error: if everything is invalid (or empty list provided)
        if (empty($valid_list)) {
            return ApiErrors::enrichError([
                'result' => 'ERROR',
                'code' => 'ERR006',
                'description' => 'No valid phone numbers.',
                'invalid' => $rejected
            ]);
        }

        $cleaned_message = MessageUtils::clean_message($message);

        if (trim($cleaned_message) === '') {
            return ApiErrors::enrichError([
                'result' => 'ERROR',
                'code' => 'ERR009',
                'description' => 'Message is empty.',
            ]);
        }

        // Single Send Flow (<= 200 items)
        if (count($valid_list) <= 200) {
            $payload = [
                'mobile' => implode(',', $valid_list),
                'message' => $cleaned_message,
                'sender' => $this->active_sender($sender),
                'test' => $this->test_mode ? 1 : 0
            ];

            $response = $this->post('send', $payload);

            if (!empty($rejected)) {
                $response['invalid'] = $rejected;
            }

            return $response;
        }

        // Bulk flow (> 200 items)
        return $this->_send_bulk($valid_list, $rejected, $validation['raw'], $cleaned_message, $sender);
    }

    /**
     * Retrieve array of sender IDs for account.
     *
     * @return array<string, mixed>
     */
    public function senderids(): array
    {
        $response = $this->post('senderid', []);

        // We enforce standardizing output: list always in 'senderids' key if ok.
        if (isset($response['result']) && $response['result'] === 'OK') {
            $response['senderids'] = $response['senderid'] ?? [];
        }

        return $response;
    }

    /**
     * Retrieve supported country coverage prefixes.
     *
     * @return array<string, mixed>
     */
    public function coverage(): array
    {
        $response = $this->post('coverage', []);

        // Match return array shape expectations
        if (isset($response['result']) && $response['result'] === 'OK' && !isset($response['prefixes'])) {
            $response['prefixes'] = $response['list'] ?? [];
        }

        return $response;
    }
}
