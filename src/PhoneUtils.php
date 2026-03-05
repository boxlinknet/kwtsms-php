<?php

namespace KwtSMS;

class PhoneUtils
{
    /**
     * Map Arabic/Hindi numerals to Latin digits
     *
     * @var array<string, string>
     */
    public const DIGITS_MAP = [
        '٠' => '0',
        '١' => '1',
        '٢' => '2',
        '٣' => '3',
        '٤' => '4',
        '٥' => '5',
        '٦' => '6',
        '٧' => '7',
        '٨' => '8',
        '٩' => '9',
        '۰' => '0',
        '۱' => '1',
        '۲' => '2',
        '۳' => '3',
        '۴' => '4',
        '۵' => '5',
        '۶' => '6',
        '۷' => '7',
        '۸' => '8',
        '۹' => '9',
    ];

    /**
     * Convert a phone number string by stripping all non-digits, converting Arabic digits,
     * and removing leading zeros.
     *
     * @param string $phone
     * @return string
     */
    public static function normalize_phone(string $phone): string
    {
        // Convert extended/arabic digits to latin
        $phone = strtr($phone, self::DIGITS_MAP);

        // Strip everything that isn't a digit
        $phone = preg_replace('/[^\d]/', '', $phone) ?? '';

        // Strip leading zeros
        return ltrim($phone, '0');
    }

    /**
     * Validates a raw phone input. Returns array containing valid status, error message (if any),
     * and the normalized number.
     *
     * @param string $phone
     * @return array{0: bool, 1: string|null, 2: string|null}
     */
    public static function validate_phone_input(string $phone): array
    {
        $phone = trim($phone);

        // 1. Check for empty
        if ($phone === '') {
            return [false, "Phone number is required", null];
        }

        // 2. Check for email
        if (strpos($phone, '@') !== false) {
            return [false, "'{$phone}' is an email address, not a phone number", null];
        }

        // 3. Normalize
        $normalized = self::normalize_phone($phone);

        // 4. No digits found
        if ($normalized === '') {
            return [false, "'{$phone}' is not a valid phone number, no digits found", null];
        }

        // 5. Check length
        $length = strlen($normalized);
        if ($length < 7) {
            return [false, "'{$phone}' is too short ({$length} digits, minimum is 7)", null];
        }

        if ($length > 15) {
            return [false, "'{$phone}' is too long ({$length} digits, maximum is 15)", null];
        }

        // 6. Valid!
        return [true, null, $normalized];
    }
}
