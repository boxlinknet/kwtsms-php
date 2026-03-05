<?php

namespace KwtSMS;

class MessageUtils
{
    /**
     * Map Arabic/Hindi numerals to Latin digits
     * @var array<string, string>
     */
    private const DIGITS_MAP = [
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
     * Hidden control characters explicitly listed in the PRD.
     * U+200B (Zero-width space)
     * U+200C (Zero-width non-joiner)
     * U+200D (Zero-width joiner)
     * U+2060 (Word joiner)
     * U+00AD (Soft hyphen)
     * U+FEFF (Byte order mark)
     * U+FFFC (Object replacement character)
     * @var array<string, string>
     */
    private const HIDDEN_CHARS = [
        "\xE2\x80\x8B" => '',
        "\xE2\x80\x8C" => '',
        "\xE2\x80\x8D" => '',
        "\xE2\x81\xA0" => '',
        "\xC2\xAD" => '',
        "\xEF\xBB\xBF" => '',
        "\xEF\xBF\xBC" => '',
    ];

    /**
     * Clean a message by stripping emojis, bad Unicode, and converting numerals.
     *
     * @param string $text
     * @return string
     */
    public static function clean_message(string $text): string
    {
        // 1. Strip HTML tags
        $text = strip_tags($text);

        // 2. Convert Arabic/Hindi numerals to Latin digits
        $text = strtr($text, self::DIGITS_MAP);

        // 3. Strip problematic hidden control characters exactly matching the PRD
        $text = strtr($text, self::HIDDEN_CHARS);

        // 4. Remove emojis and pictographic symbols handling Unicode Ranges.
        // PHP pattern modifiers: 
        // /u = utf-8 execution
        // /x = ignores whitespace in regex
        // Reference Ranges:
        // 0x1F300–0x1FAFF (Emoticons, symbols, pictographs, incl 🔥 0x1F525)
        // 0x2600–0x27BF   (Misc symbols, dingbats)
        // 0xFE00-0xFE0F   (Variation selectors for emoji/text presentation)
        $text = preg_replace(
            '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]/u',
            '',
            $text
        ) ?? $text;

        // 5. Remove remaining control characters EXCEPT \n, \r, and \t
        // We do this by targeting ASCII control chars directly (0x00 to 0x1F, except 0x09, 0x0A, 0x0D)
        // and 0x7F (Delete). 
        $text = preg_replace('/[\x{00}-\x{08}\x{0B}\x{0C}\x{0E}-\x{1F}\x{7F}]/u', '', $text) ?? $text;

        return $text;
    }
}
