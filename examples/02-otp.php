<?php

/**
 * Example 02: OTP / Verification Codes
 * --------------------------------------
 * The most common use case: sending a one-time password to a user during
 * sign-up, login, or a sensitive action (password reset, payment, etc.)
 *
 * Key production requirements for OTP:
 *   - Use a TRANSACTIONAL Sender ID (bypasses DND filters, ensures delivery)
 *   - Enable CAPTCHA on the form to prevent abuse
 *   - Rate-limit per phone number (max 3-5 OTP requests per hour)
 *   - Never expose kwtSMS errors directly to end users
 *
 * Run:
 *   php examples/02-otp.php
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\KwtSMS;

// ── Helper: Generate a secure 6-digit OTP ───────────────────────────────────
function generate_otp(int $digits = 6): string
{
    return str_pad((string) random_int(0, 10 ** $digits - 1), $digits, '0', STR_PAD_LEFT);
}

// ── Helper: Send OTP, hide internal errors from end users ───────────────────
function send_otp(KwtSMS $sms, string $phone, string $app_name): array
{
    $otp = generate_otp();
    $message = "Your {$app_name} verification code is: {$otp}\nValid for 10 minutes. Do not share this code.";

    $result = $sms->send($phone, $message);

    if ($result['result'] === 'OK') {
        return [
            'success' => true,
            'otp' => $otp,   // Store this in your session/cache to verify later
            'msg_id' => $result['msg-id'],
        ];
    }

    // ── Map internal API errors to user-safe messages ────────────────────────
    //
    // IMPORTANT: Never show raw API error codes (ERR003, ERR010, etc.) to users.
    // They reveal infrastructure details. Map them to friendly messages instead.
    //
    $code = $result['code'] ?? '';

    if ($code === 'ERR006' || $code === 'ERR025') {
        $user_message = 'Please enter a valid phone number including the country code (e.g., +965 9876 5432).';
    } elseif ($code === 'ERR026') {
        $user_message = 'SMS delivery to this country is not currently available.';
    } elseif ($code === 'ERR028') {
        $user_message = 'Please wait a moment before requesting another code.';
    } elseif ($code === 'ERR031' || $code === 'ERR032') {
        $user_message = 'Your request could not be processed. Please contact support.';
    } else {
        $user_message = 'Could not send verification code. Please try again.';
    }

    // Log the real error internally for debugging
    error_log("[kwtSMS OTP] Failed for {$phone}: [{$result['code']}] {$result['description']}");

    return [
        'success' => false,
        'user_message' => $user_message,
    ];
}

// ── Example usage ────────────────────────────────────────────────────────────

$sms = KwtSMS::from_env();

$phone = '96598765432'; // In real usage, this comes from user input

echo "Sending OTP to {$phone}...\n\n";

$result = send_otp($sms, $phone, 'MyApp');

if ($result['success']) {
    echo "✅ OTP sent!\n";
    echo "   Code to verify: {$result['otp']}\n";
    echo "   Message ID: {$result['msg_id']}\n\n";
    echo "   (In production: store the OTP in your session/cache and compare when user submits the form)\n";
} else {
    echo "❌ {$result['user_message']}\n";
}
