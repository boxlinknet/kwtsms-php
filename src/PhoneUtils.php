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
     * Phone number validation rules by country code.
     * Each entry has:
     *   localLengths: valid digit counts AFTER the country code
     *   mobileStartDigits: (optional) valid first digit(s) of the local part
     *
     * Countries not listed pass through with generic E.164 validation (7-15 digits).
     */
    public const PHONE_RULES = [
        // === GCC ===
        '965' => ['localLengths' => [8], 'mobileStartDigits' => ['4', '5', '6', '9']],       // Kuwait
        '966' => ['localLengths' => [9], 'mobileStartDigits' => ['5']],                       // Saudi Arabia
        '971' => ['localLengths' => [9], 'mobileStartDigits' => ['5']],                       // UAE
        '973' => ['localLengths' => [8], 'mobileStartDigits' => ['3', '6']],                  // Bahrain
        '974' => ['localLengths' => [8], 'mobileStartDigits' => ['3', '5', '6', '7']],        // Qatar
        '968' => ['localLengths' => [8], 'mobileStartDigits' => ['7', '9']],                  // Oman
        // === Levant ===
        '962' => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Jordan
        '961' => ['localLengths' => [7, 8], 'mobileStartDigits' => ['3', '7', '8']],          // Lebanon
        '970' => ['localLengths' => [9], 'mobileStartDigits' => ['5']],                       // Palestine
        '964' => ['localLengths' => [10], 'mobileStartDigits' => ['7']],                      // Iraq
        '963' => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Syria
        // === Other Arab ===
        '967' => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Yemen
        '20'  => ['localLengths' => [10], 'mobileStartDigits' => ['1']],                      // Egypt
        '218' => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Libya
        '216' => ['localLengths' => [8], 'mobileStartDigits' => ['2', '4', '5', '9']],        // Tunisia
        '212' => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7']],                  // Morocco
        '213' => ['localLengths' => [9], 'mobileStartDigits' => ['5', '6', '7']],             // Algeria
        '249' => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Sudan
        // === Non-Arab Middle East ===
        '98'  => ['localLengths' => [10], 'mobileStartDigits' => ['9']],                      // Iran
        '90'  => ['localLengths' => [10], 'mobileStartDigits' => ['5']],                      // Turkey
        '972' => ['localLengths' => [9], 'mobileStartDigits' => ['5']],                       // Israel
        // === South Asia ===
        '91'  => ['localLengths' => [10], 'mobileStartDigits' => ['6', '7', '8', '9']],       // India
        '92'  => ['localLengths' => [10], 'mobileStartDigits' => ['3']],                      // Pakistan
        '880' => ['localLengths' => [10], 'mobileStartDigits' => ['1']],                      // Bangladesh
        '94'  => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Sri Lanka
        '960' => ['localLengths' => [7], 'mobileStartDigits' => ['7', '9']],                  // Maldives
        // === East Asia ===
        '86'  => ['localLengths' => [11], 'mobileStartDigits' => ['1']],                      // China
        '81'  => ['localLengths' => [10], 'mobileStartDigits' => ['7', '8', '9']],            // Japan
        '82'  => ['localLengths' => [10], 'mobileStartDigits' => ['1']],                      // South Korea
        '886' => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Taiwan
        // === Southeast Asia ===
        '65'  => ['localLengths' => [8], 'mobileStartDigits' => ['8', '9']],                  // Singapore
        '60'  => ['localLengths' => [9, 10], 'mobileStartDigits' => ['1']],                   // Malaysia
        '62'  => ['localLengths' => [9, 10, 11, 12], 'mobileStartDigits' => ['8']],           // Indonesia
        '63'  => ['localLengths' => [10], 'mobileStartDigits' => ['9']],                      // Philippines
        '66'  => ['localLengths' => [9], 'mobileStartDigits' => ['6', '8', '9']],             // Thailand
        '84'  => ['localLengths' => [9], 'mobileStartDigits' => ['3', '5', '7', '8', '9']],  // Vietnam
        '95'  => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Myanmar
        '855' => ['localLengths' => [8, 9], 'mobileStartDigits' => ['1', '6', '7', '8', '9']], // Cambodia
        '976' => ['localLengths' => [8], 'mobileStartDigits' => ['6', '8', '9']],             // Mongolia
        // === Europe ===
        '44'  => ['localLengths' => [10], 'mobileStartDigits' => ['7']],                      // UK
        '33'  => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7']],                  // France
        '49'  => ['localLengths' => [10, 11], 'mobileStartDigits' => ['1']],                  // Germany
        '39'  => ['localLengths' => [10], 'mobileStartDigits' => ['3']],                      // Italy
        '34'  => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7']],                  // Spain
        '31'  => ['localLengths' => [9], 'mobileStartDigits' => ['6']],                       // Netherlands
        '32'  => ['localLengths' => [9]],                                                      // Belgium: mobile/fixed share number space; length-only intentional
        '41'  => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Switzerland
        '43'  => ['localLengths' => [10], 'mobileStartDigits' => ['6']],                      // Austria
        '47'  => ['localLengths' => [8], 'mobileStartDigits' => ['4', '9']],                  // Norway
        '48'  => ['localLengths' => [9]],                                                      // Poland: complex prefix allocation; length-only intentional
        '30'  => ['localLengths' => [10], 'mobileStartDigits' => ['6']],                      // Greece
        '420' => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7']],                  // Czech Republic
        '46'  => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Sweden
        '45'  => ['localLengths' => [8]],                                                      // Denmark: complex prefix allocation; length-only intentional
        '40'  => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Romania
        '36'  => ['localLengths' => [9]],                                                      // Hungary: complex prefix allocation; length-only intentional
        '380' => ['localLengths' => [9]],                                                      // Ukraine: complex prefix allocation; length-only intentional
        // === Americas ===
        '1'   => ['localLengths' => [10]],                                                     // USA/Canada: no mobile-specific prefix; length-only intentional
        '52'  => ['localLengths' => [10]],                                                     // Mexico: mobile/fixed unified since 2019; length-only intentional
        '55'  => ['localLengths' => [11]],                                                     // Brazil: area code + 9 + subscriber; length-only intentional
        '57'  => ['localLengths' => [10], 'mobileStartDigits' => ['3']],                      // Colombia
        '54'  => ['localLengths' => [10], 'mobileStartDigits' => ['9']],                      // Argentina
        '56'  => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Chile
        '58'  => ['localLengths' => [10], 'mobileStartDigits' => ['4']],                      // Venezuela
        '51'  => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Peru
        '593' => ['localLengths' => [9], 'mobileStartDigits' => ['9']],                       // Ecuador
        '53'  => ['localLengths' => [8], 'mobileStartDigits' => ['5', '6']],                  // Cuba
        // === Africa ===
        '27'  => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7', '8']],             // South Africa
        '234' => ['localLengths' => [10], 'mobileStartDigits' => ['7', '8', '9']],            // Nigeria
        '254' => ['localLengths' => [9], 'mobileStartDigits' => ['1', '7']],                  // Kenya
        '233' => ['localLengths' => [9], 'mobileStartDigits' => ['2', '5']],                  // Ghana
        '251' => ['localLengths' => [9], 'mobileStartDigits' => ['7', '9']],                  // Ethiopia
        '255' => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7']],                  // Tanzania
        '256' => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Uganda
        '237' => ['localLengths' => [9], 'mobileStartDigits' => ['6']],                       // Cameroon
        '225' => ['localLengths' => [10]],                                                     // Ivory Coast: 01/05/07 prefix system; length-only intentional
        '221' => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Senegal
        '252' => ['localLengths' => [9], 'mobileStartDigits' => ['6', '7']],                  // Somalia
        '250' => ['localLengths' => [9], 'mobileStartDigits' => ['7']],                       // Rwanda
        // === Oceania ===
        '61'  => ['localLengths' => [9], 'mobileStartDigits' => ['4']],                       // Australia
        '64'  => ['localLengths' => [8, 9, 10], 'mobileStartDigits' => ['2']],                // New Zealand
    ];

    /**
     * Human-readable country names keyed by country code string.
     */
    public const COUNTRY_NAMES = [
        // Middle East & North Africa
        '965' => 'Kuwait',
        '966' => 'Saudi Arabia',
        '971' => 'UAE',
        '973' => 'Bahrain',
        '974' => 'Qatar',
        '968' => 'Oman',
        '962' => 'Jordan',
        '961' => 'Lebanon',
        '970' => 'Palestine',
        '964' => 'Iraq',
        '963' => 'Syria',
        '967' => 'Yemen',
        '98'  => 'Iran',
        '90'  => 'Turkey',
        '972' => 'Israel',
        '20'  => 'Egypt',
        '218' => 'Libya',
        '216' => 'Tunisia',
        '212' => 'Morocco',
        '213' => 'Algeria',
        '249' => 'Sudan',
        '211' => 'South Sudan',
        // Africa
        '27'  => 'South Africa',
        '234' => 'Nigeria',
        '254' => 'Kenya',
        '233' => 'Ghana',
        '251' => 'Ethiopia',
        '255' => 'Tanzania',
        '256' => 'Uganda',
        '237' => 'Cameroon',
        '225' => 'Ivory Coast',
        '221' => 'Senegal',
        '252' => 'Somalia',
        '250' => 'Rwanda',
        // Europe
        '44'  => 'UK',
        '33'  => 'France',
        '49'  => 'Germany',
        '39'  => 'Italy',
        '34'  => 'Spain',
        '31'  => 'Netherlands',
        '32'  => 'Belgium',
        '41'  => 'Switzerland',
        '43'  => 'Austria',
        '46'  => 'Sweden',
        '47'  => 'Norway',
        '45'  => 'Denmark',
        '48'  => 'Poland',
        '420' => 'Czech Republic',
        '36'  => 'Hungary',
        '40'  => 'Romania',
        '30'  => 'Greece',
        '380' => 'Ukraine',
        // Americas
        '1'   => 'USA/Canada',
        '52'  => 'Mexico',
        '55'  => 'Brazil',
        '54'  => 'Argentina',
        '57'  => 'Colombia',
        '56'  => 'Chile',
        '58'  => 'Venezuela',
        '51'  => 'Peru',
        '593' => 'Ecuador',
        '53'  => 'Cuba',
        // Asia
        '91'  => 'India',
        '92'  => 'Pakistan',
        '86'  => 'China',
        '81'  => 'Japan',
        '82'  => 'South Korea',
        '886' => 'Taiwan',
        '65'  => 'Singapore',
        '60'  => 'Malaysia',
        '62'  => 'Indonesia',
        '63'  => 'Philippines',
        '66'  => 'Thailand',
        '84'  => 'Vietnam',
        '855' => 'Cambodia',
        '95'  => 'Myanmar',
        '880' => 'Bangladesh',
        '94'  => 'Sri Lanka',
        '960' => 'Maldives',
        '976' => 'Mongolia',
        // Oceania
        '61'  => 'Australia',
        '64'  => 'New Zealand',
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
     * Find the country code prefix from a normalized phone number.
     * Tries 3-digit codes first, then 2-digit, then 1-digit (longest match wins).
     *
     * @param string $normalized Digits-only phone number with no leading zeros
     * @return string|null Country code string, or null if not found in PHONE_RULES
     */
    public static function find_country_code(string $normalized): ?string
    {
        if (strlen($normalized) >= 3) {
            $cc3 = substr($normalized, 0, 3);
            if (isset(self::PHONE_RULES[$cc3])) {
                return $cc3;
            }
        }
        if (strlen($normalized) >= 2) {
            $cc2 = substr($normalized, 0, 2);
            if (isset(self::PHONE_RULES[$cc2])) {
                return $cc2;
            }
        }
        if (strlen($normalized) >= 1) {
            $cc1 = substr($normalized, 0, 1);
            if (isset(self::PHONE_RULES[$cc1])) {
                return $cc1;
            }
        }
        return null;
    }

    /**
     * Validate a normalized phone number against country-specific format rules.
     * Checks local number length and mobile starting digit.
     * Numbers with no matching country rules pass through (generic E.164 only).
     *
     * @param string $normalized Digits-only phone number with no leading zeros
     * @return array{0: bool, 1: string|null} [isValid, errorMessage]
     */
    public static function validate_phone_format(string $normalized): array
    {
        $cc = self::find_country_code($normalized);
        if ($cc === null) {
            return [true, null];
        }

        $rule    = self::PHONE_RULES[$cc];
        $local   = substr($normalized, strlen($cc));
        $country = self::COUNTRY_NAMES[$cc] ?? "+{$cc}";

        // Check local number length
        if (!in_array(strlen($local), $rule['localLengths'], true)) {
            $expected = implode(' or ', $rule['localLengths']);
            return [
                false,
                "Invalid {$country} number: expected {$expected} digits after +{$cc}, got " . strlen($local),
            ];
        }

        // Check mobile starting digit (if rules exist for this country)
        if (!empty($rule['mobileStartDigits'])) {
            $firstDigit = $local[0] ?? '';
            if (!in_array($firstDigit, $rule['mobileStartDigits'], true)) {
                $prefixes = implode(', ', $rule['mobileStartDigits']);
                return [
                    false,
                    "Invalid {$country} mobile number: after +{$cc} must start with {$prefixes}",
                ];
            }
        }

        return [true, null];
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

        if ($phone === '') {
            return [false, "Phone number is required", null];
        }

        if (strpos($phone, '@') !== false) {
            return [false, "'{$phone}' is an email address, not a phone number", null];
        }

        $normalized = self::normalize_phone($phone);

        if ($normalized === '') {
            return [false, "'{$phone}' is not a valid phone number, no digits found", null];
        }

        // Strip local trunk digit '0' when a known country code is matched.
        // E.g. "966 0 55 123 4567" normalizes to "9660551234567"; stripping the
        // trunk zero gives "966551234567" which satisfies the Saudi 9-digit rule.
        $cc = self::find_country_code($normalized);
        if ($cc !== null) {
            $local = substr($normalized, strlen($cc));
            if ($local !== '' && $local[0] === '0') {
                $normalized = $cc . substr($local, 1);
            }
        }

        $length = strlen($normalized);
        if ($length < 7) {
            return [false, "'{$phone}' is too short ({$length} digits, minimum is 7)", null];
        }

        if ($length > 15) {
            return [false, "'{$phone}' is too long ({$length} digits, maximum is 15)", null];
        }

        [$formatValid, $formatError] = self::validate_phone_format($normalized);
        if (!$formatValid) {
            return [false, $formatError, null];
        }

        return [true, null, $normalized];
    }
}
