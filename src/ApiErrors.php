<?php

namespace KwtSMS;

class ApiErrors
{
    /**
     * Map of kwtSMS error codes to developer-friendly descriptions and action messages.
     *
     * @var array<string, array{description: string, action: string}>
     */
    public const ERRORS = [
        'ERR001' => [
            'description' => 'API is disabled on this account.',
            'action' => 'Enable it at kwtsms.com → Account → API.',
        ],
        'ERR002' => [
            'description' => 'A required parameter is missing.',
            'action' => 'Check that username, password, sender, mobile, and message are all provided.',
        ],
        'ERR003' => [
            'description' => 'Wrong API username or password.',
            'action' => 'Check KWTSMS_USERNAME and KWTSMS_PASSWORD. These are your API credentials, not your account mobile number.',
        ],
        'ERR004' => [
            'description' => 'This account does not have API access.',
            'action' => 'Contact kwtSMS support to enable it.',
        ],
        'ERR005' => [
            'description' => 'This account is blocked.',
            'action' => 'Contact kwtSMS support.',
        ],
        'ERR006' => [
            'description' => 'No valid phone numbers.',
            'action' => 'Make sure each number includes the country code (e.g., 96598765432 for Kuwait, not 98765432).',
        ],
        'ERR007' => [
            'description' => 'Too many numbers in a single request (maximum 200).',
            'action' => 'Split into smaller batches.',
        ],
        'ERR008' => [
            'description' => 'This sender ID is banned.',
            'action' => 'Use a different sender ID registered on your kwtSMS account.',
        ],
        'ERR009' => [
            'description' => 'Message is empty.',
            'action' => 'Provide a non-empty message text.',
        ],
        'ERR010' => [
            'description' => 'Account balance is zero.',
            'action' => 'Recharge credits at kwtsms.com.',
        ],
        'ERR011' => [
            'description' => 'Insufficient balance for this send.',
            'action' => 'Buy more credits at kwtsms.com.',
        ],
        'ERR012' => [
            'description' => 'Message is too long (over 7 SMS pages).',
            'action' => 'Shorten your message.',
        ],
        'ERR013' => [
            'description' => 'Send queue is full (1000 messages).',
            'action' => 'Wait a moment and try again.',
        ],
        'ERR019' => [
            'description' => 'No delivery reports found for this message.',
            'action' => 'Check the message ID.',
        ],
        'ERR020' => [
            'description' => 'Message ID does not exist.',
            'action' => 'Make sure you saved the msg-id from the send response.',
        ],
        'ERR021' => [
            'description' => 'No delivery report available for this message yet.',
            'action' => 'Try again later.',
        ],
        'ERR022' => [
            'description' => 'Delivery reports are not ready yet.',
            'action' => 'Try again after 24 hours.',
        ],
        'ERR023' => [
            'description' => 'Unknown delivery report error.',
            'action' => 'Contact kwtSMS support.',
        ],
        'ERR024' => [
            'description' => 'Your IP address is not in the API whitelist.',
            'action' => 'Add it at kwtsms.com → Account → API → IP Lockdown, or disable IP lockdown.',
        ],
        'ERR025' => [
            'description' => 'Invalid phone number.',
            'action' => 'Make sure the number includes the country code (e.g., 96598765432 for Kuwait, not 98765432).',
        ],
        'ERR026' => [
            'description' => 'This country is not activated on your account.',
            'action' => 'Contact kwtSMS support to enable the destination country.',
        ],
        'ERR027' => [
            'description' => 'HTML tags are not allowed in the message.',
            'action' => 'Remove any HTML content and try again.',
        ],
        'ERR028' => [
            'description' => 'You must wait at least 15 seconds before sending to the same number again. No credits were consumed.',
            'action' => 'Wait 15 seconds before sending.',
        ],
        'ERR029' => [
            'description' => 'Message ID does not exist or is incorrect.',
            'action' => 'Check the message ID.',
        ],
        'ERR030' => [
            'description' => 'Message is stuck in the send queue with an error.',
            'action' => 'Delete it at kwtsms.com → Queue to recover credits.',
        ],
        'ERR031' => [
            'description' => 'Message rejected: bad language detected.',
            'action' => 'Remove bad language and try again.',
        ],
        'ERR032' => [
            'description' => 'Message rejected: spam detected.',
            'action' => 'Check message content and try again.',
        ],
        'ERR033' => [
            'description' => 'No active coverage found.',
            'action' => 'Contact kwtSMS support.',
        ],
        'ERR_INVALID_INPUT' => [
            'description' => 'One or more phone numbers are invalid.',
            'action' => 'See details above.',
        ],
    ];

    /**
     * Enrich an raw API error dictionary with action context.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    public static function enrichError(array $response): array
    {
        $code = isset($response['code']) ? (string) $response['code'] : 'ERR999';

        // If the API didn't return a description, attempt to get our own translation
        if (!isset($response['description']) && isset(self::ERRORS[$code])) {
            $response['description'] = self::ERRORS[$code]['description'];
        }

        // Add our action mapping only if the response doesn't already carry one
        if (isset(self::ERRORS[$code]) && !isset($response['action'])) {
            $response['action'] = self::ERRORS[$code]['action'];
        }

        return $response;
    }
}
