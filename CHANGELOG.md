# Changelog

All notable changes to `kwtsms/kwtsms` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [1.0.0] — 2026-03-05

### Added
- `KwtSMS` main client class with zero external dependencies
- `KwtSMS::from_env()` — load credentials from environment variables or `.env` file
- `KwtSMS::verify()` — test credentials and retrieve current balance
- `KwtSMS::balance()` — retrieve current credit balance
- `KwtSMS::send()` — send SMS to one or more recipients (single and bulk)
- `KwtSMS::validate()` — validate phone numbers locally without an API call
- `KwtSMS::senderids()` — list sender IDs registered on the account
- `KwtSMS::coverage()` — list active country coverage prefixes
- **Bulk sending** — automatic batching for >200 numbers with 0.5s inter-batch delay
- **ERR013 retry** — automatic queue-full backoff (30s → 60s → 120s)
- `PhoneUtils::normalize_phone()` — normalize phone numbers (Arabic digits, `+`, `00`, spaces, dashes)
- `PhoneUtils::validate_phone_input()` — validate phone numbers with detailed error messages
- `MessageUtils::clean_message()` — strip emojis, hidden Unicode, HTML, and convert Arabic digits
- `ApiErrors` — all 33 kwtSMS error codes mapped to developer-friendly descriptions and action messages
- JSONL logging with automatic password masking
- PHP 7.4+ compatibility
- PSR-4 autoloading under the `KwtSMS\` namespace
- `phpunit/phpunit` — unit and integration test suite (18 tests)
- `phpstan/phpstan` — static analysis (Level 0)
- `friendsofphp/php-cs-fixer` — code style enforcement
- MIT License
- Comprehensive `README.md` with Quick Start, API reference, security checklist, and FAQ
- 8 examples: quickstart, OTP, bulk, validation, error handling, message cleaning, Laravel, WordPress

---

## Version History

| Version | Date | PHP | Notable Change |
|---------|------|-----|----------------|
| 1.0.0 | 2026-03-05 | 7.4+ | Initial release |

---

[Unreleased]: https://github.com/boxlinknet/kwtsms-php/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/boxlinknet/kwtsms-php/releases/tag/v1.0.0
