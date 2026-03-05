<?php

/**
 * Example 07: Laravel Integration
 * --------------------------------
 * How to integrate kwtSMS into a Laravel application cleanly using:
 *   - A Service Provider to register the client as a singleton
 *   - Config file for credentials
 *   - A Facade for static-style access
 *   - A Notification class for the Laravel Notifications system
 *
 * Installation:
 *   composer require kwtsms/kwtsms
 *
 * NOTE: This file is a reference guide. Copy the relevant classes into your
 *       Laravel application structure. Do not run this file directly.
 */

// ════════════════════════════════════════════════════════════════════════════
// 1. config/kwtsms.php
//    Publish: php artisan vendor:publish (or create manually)
// ════════════════════════════════════════════════════════════════════════════

/*
return [
    'username'  => env('KWTSMS_USERNAME'),
    'password'  => env('KWTSMS_PASSWORD'),
    'sender_id' => env('KWTSMS_SENDER_ID', 'KWT-SMS'),
    'test_mode' => env('KWTSMS_TEST_MODE', false),
    'log_file'  => env('KWTSMS_LOG_FILE', storage_path('logs/kwtsms.log')),
];
*/


// ════════════════════════════════════════════════════════════════════════════
// 2. app/Providers/KwtSmsServiceProvider.php
// ════════════════════════════════════════════════════════════════════════════

/*
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use KwtSMS\KwtSMS;

class KwtSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(KwtSMS::class, function ($app) {
            $config = $app['config']['kwtsms'];
            return new KwtSMS(
                $config['username'],
                $config['password'],
                $config['sender_id'],
                $config['test_mode'],
                $config['log_file'],
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/kwtsms.php' => config_path('kwtsms.php'),
        ], 'kwtsms-config');
    }
}
*/


// ════════════════════════════════════════════════════════════════════════════
// 3. Usage in a Controller
// ════════════════════════════════════════════════════════════════════════════

/*
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use KwtSMS\KwtSMS;

class AuthController extends Controller
{
    public function __construct(private KwtSMS $sms) {}

    public function sendOtp(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['phone' => 'required|string']);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 10 minutes
        cache()->put('otp_' . $request->phone, $otp, now()->addMinutes(10));

        $result = $this->sms->send(
            $request->phone,
            "Your verification code is: {$otp}\nExpires in 10 minutes."
        );

        if ($result['result'] !== 'OK') {
            // Log internal error, return generic message to user
            logger()->error('kwtSMS OTP failed', $result);
            return response()->json(['message' => 'Could not send verification code. Please try again.'], 503);
        }

        return response()->json(['message' => 'Verification code sent.']);
    }

    public function verifyOtp(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['phone' => 'required|string', 'otp' => 'required|string']);

        $cached = cache()->get('otp_' . $request->phone);

        if (!$cached || $cached !== $request->otp) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        cache()->forget('otp_' . $request->phone);

        // Mark phone as verified, issue token, etc.
        return response()->json(['message' => 'Phone verified successfully.']);
    }
}
*/


// ════════════════════════════════════════════════════════════════════════════
// 4. Laravel Notification Channel (app/Notifications/SmsNotification.php)
// ════════════════════════════════════════════════════════════════════════════

/*
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use KwtSMS\KwtSMS;

class SmsNotification extends Notification
{
    public function __construct(private string $message) {}

    public function via(mixed $notifiable): array
    {
        return ['sms'];
    }

    public function toSms(mixed $notifiable): string
    {
        return $this->message;
    }
}

// Channel class (app/Channels/SmsChannel.php)
class SmsChannel
{
    public function __construct(private KwtSMS $sms) {}

    public function send(mixed $notifiable, SmsNotification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms');
        $message = $notification->toSms($notifiable);
        $this->sms->send($phone, $message);
    }
}

// Usage:
// $user->notify(new SmsNotification('Your appointment is confirmed for tomorrow at 10am.'));
*/


// ════════════════════════════════════════════════════════════════════════════
// 5. .env additions for Laravel
// ════════════════════════════════════════════════════════════════════════════

/*
KWTSMS_USERNAME=php_username
KWTSMS_PASSWORD=php_password
KWTSMS_SENDER_ID=MY-BRAND
KWTSMS_TEST_MODE=false
KWTSMS_LOG_FILE=
*/

echo "This file is a Laravel integration reference guide.\n";
echo "Copy the class snippets above into your Laravel application.\n";
