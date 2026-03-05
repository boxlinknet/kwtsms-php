<?php

/**
 * Example 05: Error Handling
 * ---------------------------
 * Comprehensive guide to handling every category of kwtSMS API error.
 *
 * The library automatically enriches every error response with an 'action'
 * field telling you exactly what to do. This example shows how to handle
 * each error category correctly in a production application.
 *
 * Run:
 *   php examples/05-error-handling.php
 */

require __DIR__ . '/../vendor/autoload.php';

use KwtSMS\KwtSMS;
use KwtSMS\ApiErrors;

$sms = KwtSMS::from_env();

// ── Helper: A robust send wrapper for production use ─────────────────────────

function send_safe(KwtSMS $sms, string $phone, string $message): void
{
    $result = $sms->send($phone, $message);

    if ($result['result'] === 'OK') {
        echo "✅ Sent! ID: {$result['msg-id']}, Cost: {$result['points-charged']} credits\n";
        return;
    }

    $code = $result['code'] ?? 'UNKNOWN';
    $desc = $result['description'] ?? 'Unknown error';
    $action = $result['action'] ?? '';

    echo "❌ Error [{$code}]: {$desc}\n";
    if ($action) {
        echo "   Action: {$action}\n";
    }

    // ── Handle specific error categories ─────────────────────────────────────

    switch ($code) {
        // ── Auth errors: alert the admin, do not retry ───────────────────────
        case 'ERR001':
        case 'ERR003':
        case 'ERR004':
        case 'ERR005':
            echo "   ⚠️  ADMIN ALERT: Credential or account issue. Check kwtSMS dashboard.\n";
            // In production: send an alert email/Slack to your ops team
            break;

        // ── Balance errors: alert admin to top up ────────────────────────────
        case 'ERR010':
        case 'ERR011':
            echo "   ⚠️  ADMIN ALERT: Low or zero balance. Recharge at kwtsms.com.\n";
            // In production: trigger a low-balance notification
            break;

        // ── Invalid number: log it, do not retry ────────────────────────────
        case 'ERR006':
        case 'ERR025':
            echo "   ℹ️  Invalid number. Check format includes country code.\n";
            break;

        // ── Country not enabled: contact kwtSMS support ──────────────────────
        case 'ERR026':
            echo "   ℹ️  Destination country not activated. Contact kwtSMS support.\n";
            break;

        // ── Sender ID issue: check spelling/registration ─────────────────────
        case 'ERR008':
            echo "   ℹ️  Sender ID not found or banned. Check sender ID registration.\n";
            break;

        // ── Rate limit: wait 15 seconds, then retry once ─────────────────────
        case 'ERR028':
            echo "   ℹ️  Rate limited. Waiting 15 seconds before retry...\n";
            sleep(15);
            $retry = $sms->send($phone, $message);
            echo "   Retry result: {$retry['result']}\n";
            break;

        // ── Queue full: auto-handled in bulk, manual retry for single ─────────
        case 'ERR013':
            echo "   ℹ️  Queue full. Retry in 30 seconds.\n";
            break;

        // ── IP lockdown: add server IP to whitelist ───────────────────────────
        case 'ERR024':
            echo "   ⚠️  Your server IP is not whitelisted. Add it at kwtsms.com → API → IP Lockdown.\n";
            break;

        default:
            echo "   ℹ️  Unexpected error. Check kwtsms.log for details.\n";
    }
}

// ── Demonstrate verify() error handling ─────────────────────────────────────

echo "═══ CREDENTIAL VERIFICATION ═══\n\n";

[$ok, $balance, $error] = $sms->verify();

if ($ok) {
    echo "✅ Credentials valid. Balance: {$balance} credits\n\n";
} else {
    echo "❌ Verification failed: {$error}\n\n";
    // In production, stop here — don't attempt sends if credentials are bad
    exit(1);
}

// ── Demonstrate send() error handling ───────────────────────────────────────

echo "═══ SEND ERROR HANDLING ═══\n\n";

// Valid number — should succeed
echo "Test 1: Valid number\n";
send_safe($sms, '96598765432', 'Error handling test 1');

echo "\nTest 2: Invalid number\n";
send_safe($sms, 'not-a-phone', 'Error handling test 2');

echo "\nTest 3: Email entered by mistake\n";
send_safe($sms, 'user@example.com', 'Error handling test 3');

// ── Inspect all error codes ──────────────────────────────────────────────────

echo "\n═══ ALL MAPPED ERROR CODES ═══\n\n";

foreach (ApiErrors::ERRORS as $code => $info) {
    echo "  [{$code}] {$info['description']}\n";
    echo "           → {$info['action']}\n\n";
}
