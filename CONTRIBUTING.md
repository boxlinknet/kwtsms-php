# Contributing to kwtsms/kwtsms

Thank you for taking the time to contribute. This document covers how to report
bugs, propose changes, and submit pull requests.

---

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)
- [Development Setup](#development-setup)
- [Coding Standards](#coding-standards)
- [Running Tests](#running-tests)
- [Submitting a Pull Request](#submitting-a-pull-request)
- [Versioning](#versioning)

---

## Code of Conduct

Be respectful, constructive, and professional. Contributions from all experience
levels are welcome.

---

## Getting Started

1. **Search first:** check [existing issues](https://github.com/boxlinknet/kwtsms-php/issues)
   and [open PRs](https://github.com/boxlinknet/kwtsms-php/pulls) before opening a new one.
2. **Small PRs win:** focused, single-purpose pull requests are reviewed faster.
3. **Tests are required:** all changes to `src/` must be accompanied by tests.

---

## Reporting Bugs

Open an issue at [github.com/boxlinknet/kwtsms-php/issues/new](https://github.com/boxlinknet/kwtsms-php/issues/new) and include:

- **PHP version** (`php -v`)
- **Library version** (`composer show kwtsms/kwtsms`)
- **kwtSMS error code and description** (if applicable)
- **Minimal reproducible example:** the smallest snippet that shows the problem
- **Expected** vs **actual** behaviour

> Never post API credentials in an issue. Mask usernames and passwords with `***`.

---

## Suggesting Features

Open a [GitHub Discussion](https://github.com/boxlinknet/kwtsms-php/discussions)
or an issue tagged `enhancement`. Describe the problem, your proposed solution,
and any alternatives considered.

**Zero external dependencies is a hard requirement.** Feature requests that add
packages to `src/` will not be accepted. The library must work with only
`ext-curl` and `ext-json`.

---

## Development Setup

```bash
# 1. Fork the repository on GitHub, then clone your fork
git clone https://github.com/YOUR-USERNAME/kwtsms-php.git
cd kwtsms-php

# 2. Install dev dependencies
composer install

# 3. Set up credentials for integration tests
cp .env.example .env
# Edit .env and add your kwtSMS test credentials (use KWTSMS_TEST_MODE=1)
```

`.env.example` template (no real credentials):

```ini
KWTSMS_USERNAME=
KWTSMS_PASSWORD=
KWTSMS_SENDER_ID=KWT-SMS
KWTSMS_TEST_MODE=1
KWTSMS_LOG_FILE=kwtsms.log
```

---

## Coding Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) enforced by
PHP-CS-Fixer.

```bash
# Auto-fix style issues
composer format

# Run static analysis
composer phpstan
```

Additional conventions:

- **No external dependencies** in `src/`: only `ext-curl` and `ext-json`
- **PHP 7.4+ compatibility:** no syntax or functions from PHP 8.0+ in `src/`
- **Type hints everywhere:** all public and protected methods must be fully typed
- **PHPDoc on all public methods:** include `@param`, `@return`, and `@throws`
- **Array shapes documented:** use `@return array{key: type, ...}` for structured returns
- **No silent failures:** every error must be returned or logged, never swallowed

---

## Running Tests

```bash
# Run the full test suite
composer test

# Run only unit tests (no credentials needed)
vendor/bin/phpunit tests/PhoneUtilsTest.php tests/MessageUtilsTest.php tests/ApiErrorsTest.php tests/KwtSMSClientTest.php

# Run integration tests (requires real credentials in .env)
vendor/bin/phpunit tests/IntegrationTest.php

# Run static analysis
composer phpstan

# Fix code style
composer format
```

### Test Tiers

| Tier | File(s) | Needs credentials |
|------|---------|-------------------|
| 1: Unit | `PhoneUtilsTest.php`, `MessageUtilsTest.php` | No |
| 2: Mocked API | `ApiErrorsTest.php`, `KwtSMSClientTest.php` | No |
| 3: Integration | `IntegrationTest.php` | Yes (`.env`) |

Integration tests use `KWTSMS_TEST_MODE=1`. No real messages are sent and no
credits are consumed. They are automatically skipped if `KWTSMS_USERNAME` is not
set in the environment.

---

## Submitting a Pull Request

### Step 1: Create a branch

```bash
git checkout -b fix/description-of-fix
# or
git checkout -b feat/description-of-feature
```

Branch naming:

- `fix/`: bug fixes
- `feat/`: new features
- `docs/`: documentation-only changes
- `test/`: test improvements
- `refactor/`: code restructuring without behaviour change

### Step 2: Make your changes

- Keep changes focused on a single concern
- Update or add tests for any changed behaviour
- Add an entry to `CHANGELOG.md` under `[Unreleased]`
- Run the full test suite and confirm it passes

```bash
composer test
composer phpstan
composer format
```

### Step 3: Commit with a clear message

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```
type(scope): short summary in present tense

Optional longer description explaining WHY the change was made.

Fixes #123
```

Examples:

```
fix(message-utils): extend emoji Unicode range to cover country flags and tags block
feat(cli): add bin/kwtsms send and verify commands
docs(readme): document purchased() method and CLI usage
test(client): add mocked bulk-send and ERR009 emoji-only tests
```

### Step 4: Push and open a PR

```bash
git push origin fix/your-branch-name
```

Then open a PR at [github.com/boxlinknet/kwtsms-php/pulls](https://github.com/boxlinknet/kwtsms-php/pulls).

**PR checklist:**

- [ ] All tests pass (`composer test`)
- [ ] Static analysis passes (`composer phpstan`)
- [ ] Code style fixed (`composer format`)
- [ ] `CHANGELOG.md` updated under `[Unreleased]`
- [ ] No new external dependencies added to `src/`
- [ ] PHP 7.4 compatible (no PHP 8.0+ syntax in `src/`)
- [ ] PR description explains the problem and the solution

---

## Versioning

This project follows [Semantic Versioning](https://semver.org/):

| Change type | Version bump | Example |
|-------------|-------------|---------|
| Backwards-compatible bug fix | Patch | `1.0.0` to `1.0.1` |
| Backwards-compatible new feature | Minor | `1.0.0` to `1.1.0` |
| Breaking change | Major | `1.0.0` to `2.0.0` |

Maintainers handle version tagging and Packagist releases.

---

## Questions?

Open a [GitHub Discussion](https://github.com/boxlinknet/kwtsms-php/discussions)
for general questions. For kwtSMS API questions (not library bugs), contact
[kwtSMS support](https://www.kwtsms.com/support.html).
