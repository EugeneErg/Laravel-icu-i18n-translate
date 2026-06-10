# Laravel ICU i18n Translate

A Laravel package that wires [eugene-erg/icu-i18n-translator](https://github.com/EugeneErg/ICU-i18n-translator) into a standard Laravel application, providing Eloquent-backed repositories, auto-registered database migrations, and a pre-configured service container binding.

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Database Setup](#database-setup)
- [Configuration](#configuration)
- [Usage](#usage)
    - [Translating plain text](#translating-plain-text)
    - [Translating ICU messages](#translating-icu-messages)
    - [Adding an external AI/neural translator](#adding-an-externalai-neural-translator)
    - [Working with files and formatters](#working-with-files-and-formatters)
    - [Managing translations manually](#managing-translations-manually)
- [Comparison with Laravel's built-in localisation](#comparison-with-laravels-built-in-localisation)
- [Known Issues / Bugs Found During Testing](#known-issues--bugs-found-during-testing)
- [Testing](#testing)
- [Contributing](#contributing)

---

## Overview

This package provides the Laravel-specific **persistence layer** (Eloquent models + repositories) for the `eugene-erg/icu-i18n-translator` core library. The core library understands ICU Message Format patterns and can route translation requests through pluggable external translators (e.g. DeepL, Google Translate, GPT) and formatters (e.g. JSON, YAML localisation files). This package stores the results in your app's database so every translated string is cached and reused on subsequent requests.

### Key features

- Full ICU Message Format support: plurals, genders, selects, date/number formatting.
- Database-cached translations — the external translator API is called at most once per unique string per locale.
- Optional source-language detection when the original locale is unknown.
- File-path tree for organising translation keys the same way as traditional `lang/` files.
- Auto-discoverable via Laravel's package discovery — zero config for the `ServiceProvider`.

---

## Architecture

```
Your Application
       │
       ▼
  Translator (core: eugene-erg/icu-i18n-translator)
  ├── ICU Message Format Parser
  ├── TranslatorInterface[]       ← you implement these (DeepL, GPT, etc.)
  ├── FormatterInterface[]        ← you implement these (JSON, YAML, etc.)
  └── Repository interfaces
            │
            ▼
  This Package (laravel-icu-i18n-translate)
  ├── Eloquent Models
  │   ├── GroupModel        (icu_i18n_groups)
  │   ├── TranslateModel    (icu_i18n_translates)
  │   ├── GroupTranslateModel (icu_i18n_group_translates)
  │   └── PathModel         (icu_i18n_paths)
  └── Repositories (Read/Write) implementing the core interfaces
```

### Database schema

| Table | Purpose |
|---|---|
| `icu_i18n_groups` | One row per unique ICU pattern + context + source locale combination |
| `icu_i18n_translates` | One row per unique translated pattern per locale |
| `icu_i18n_group_translates` | Pivot: maps a group variant key to a translated pattern |
| `icu_i18n_paths` | Hierarchical key tree for file-based translation organisation |

---

## Requirements

- PHP 8.2+
- Laravel 13.3+
- `php-intl` extension (for `MessageFormatter` and ICU support)
- `php-mbstring` extension

---

## Installation

```bash
composer require eugene-erg/laravel-icu-i18n-translate
```

Laravel's package auto-discovery registers the `ServiceProvider` automatically. No entry in `config/app.php` is needed.

---

## Database Setup

Run the included migrations to create the four tables:

```bash
php artisan migrate
```

The package loads its own migrations via `$this->loadMigrationsFrom(...)` in the service provider, so the migration files don't need to be published to your `database/migrations` folder.

> **Note:** The `context` column in `icu_i18n_groups` should be nullable. If you encounter a NOT NULL constraint violation when inserting groups without context, apply the fix migration below or use a context string.

---

## Configuration

The service provider binds `Translator` into the container. Two bindings are left empty by default and can be overridden in your own service provider:

```php
// Register external translation drivers
$this->app->singleton(TranslatorInterface::class . '[]', function () {
    return [
        app(MyDeepLTranslator::class),
        app(MyOpenAiTranslator::class),
    ];
});

// Register file format drivers
$this->app->singleton(FormatterInterface::class . '[]', function () {
    return [
        'json' => app(MyJsonFormatter::class),
        'php'  => app(MyPhpArrayFormatter::class),
    ];
});
```

`TranslatorInterface` and `FormatterInterface` are defined in the `eugene-erg/icu-i18n-translator` core package.

---

## Usage

Resolve the `Translator` class from the container wherever you need it:

```php
use EugeneErg\IcuI18nTranslator\Translator;

$translator = app(Translator::class);
```

### Translating plain text

```php
// Translate "Hello world" from English to French.
// The external translator is called on the first request; the result is cached.
$result = $translator->translateText(
    text: 'Hello world',
    toLocale: 'fr',
    fromLocale: 'en',
);
// → "Bonjour le monde"

// Auto-detect the source language (requires a TranslatorInterface that supports detection)
$result = $translator->translateText(
    text: 'Hello world',
    toLocale: 'de',
);
```

### Translating ICU messages

ICU Message Format lets you embed pluralisation, date formatting, and conditional logic directly in your strings:

```php
$pattern = '{count, plural, one {# unread message} other {# unread messages}}';

$result = $translator->translateMessage(
    pattern: $pattern,
    values: ['count' => 3],
    toLocale: 'fr',
    fromLocale: 'en',
);
// → "3 messages non lus"
```

The translator understands which "case" applies for the given values, looks up or creates only the necessary database entries, and returns the formatted string.

### Adding an external AI/neural translator

Implement `TranslatorInterface` from the core package:

```php
use EugeneErg\IcuI18nTranslator\TranslatorInterface;
use EugeneErg\IcuI18nTranslator\DataTransferObjects\Variable;
use EugeneErg\IcuI18nTranslator\ValueObjects\Translated;

class DeepLTranslator implements TranslatorInterface
{
    public function canTranslate(string $toLocale, ?string $fromLocale = null): bool
    {
        return in_array($toLocale, ['fr', 'de', 'es', 'ja']);
    }

    /**
     * $pattern is an array of strings and Variable placeholders.
     * Translate only the string segments; pass Variable objects through unchanged.
     */
    public function translate(array $pattern, string $fromLocale, string $toLocale, ?string $context = null): array
    {
        return array_map(function ($part) use ($fromLocale, $toLocale) {
            if ($part instanceof Variable) {
                return $part; // Never translate placeholders
            }
            return DeepLApi::translate($part, $fromLocale, $toLocale);
        }, $pattern);
    }

    public function translateWithDetect(array $pattern, string $toLocale, ?string $context = null): Translated
    {
        $detectedLocale = 'en'; // returned by DeepL's detect API
        $translated = $this->translate($pattern, $detectedLocale, $toLocale);
        return new Translated($detectedLocale, $translated);
    }
}
```

Then register it:

```php
$this->app->singleton(TranslatorInterface::class . '[]', fn() => [
    app(DeepLTranslator::class),
]);
```

### Working with files and formatters

The `Translator` can import and export translation files in any format you provide a formatter for:

```php
// Import a JSON locale file
$translator->addFile(
    format: 'json',
    name: 'messages',
    content: file_get_contents(lang_path('fr/messages.json')),
    locale: 'fr',
);

// Export the stored translations back to JSON
$json = $translator->getFile(format: 'json', name: 'messages', locale: 'de');
file_put_contents(lang_path('de/messages.json'), $json);
```

`FormatterInterface` requires two methods: `parse(string $content): FilePathContainer` and `format(FilePathContainer $file): string`.

### Managing translations manually

You can read, update, or delete translations without going through the external API:

```php
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;

// List groups (paginated, page 1, 20 per page)
$groups = $translator->getGroups(pageSize: 20, page: 1);

// Read all translation variants for a group in a given locale
$variants = $translator->getTranslates(new GroupId('42'), 'fr');
// Returns array<string, DataTransferObjects\Translate> keyed by variant key ('0', '1', etc.)

// Manually set a translation variant
$translator->setTranslate(new GroupId('42'), key: '0', locale: 'fr', pattern: 'Bonjour');

// Remove a variant from a group
$translator->deleteTranslateFromGroup(new GroupId('42'), key: '0', locale: 'fr');
```

---

## Comparison with Laravel's built-in localisation

| Feature | Laravel `lang/` files | This package |
|---|---|---|
| Format | PHP arrays / JSON files | ICU Message Format |
| Pluralisation | `trans_choice`, `|` syntax | Full ICU `plural`, `select`, `selectordinal` |
| Date/number formatting | Manual | Built-in ICU formatting via `php-intl` |
| Auto-translation | ✗ | ✔ via pluggable external translators |
| Storage | Files | Database (cached, reusable) |
| Translation caching | None (files always read) | DB cache — external API called once per string |
| Source language detection | ✗ | ✔ (requires a detector-capable driver) |
| Context-aware translation | ✗ | ✔ via `context` parameter |
| File import/export | N/A | ✔ via `FormatterInterface` |
| PHP version | 8.1+ | 8.2+ |
| Laravel version | All | 13.3+ |

Use this package when you need machine translation, ICU-standard plural rules (especially for non-European languages), or want to manage translations from a database/admin UI. Stick with Laravel's built-in system when you manage translations manually through source-controlled files.

---

## Known Issues / Bugs Found During Testing

The following bugs were discovered during test authorship and are documented in the test suite:

**1. `icu_i18n_groups.context` column is NOT NULL in the migration**
The original migration defines `$table->string('context')` without `->nullable()`. This causes an integrity constraint violation whenever a group is created without a context. Fix: change the column definition to `$table->string('context')->nullable()`.

**2. `ReadTranslateRepository` JOIN references wrong table name**
All three join queries in `ReadTranslateRepository` reference `icu_translates.id` instead of `icu_i18n_translates.id`. On MySQL/Postgres this raises "table not found"; on SQLite it silently returns no rows. Fix: rename the table reference in all three queries to `icu_i18n_translates`.

**3. Eloquent models missing `$timestamps = false`**
The four Eloquent models do not set `public $timestamps = false`. The migrations don't create `created_at`/`updated_at` columns, so every INSERT fails with "column not found". Fix: add `public $timestamps = false` to each model.

**4. `WriteGroupTranslateRepository::deleteByGroupId` locale filter is a no-op**
The method accepts a `$locale` parameter and adds `->where('locale', '=', $locale)` to the query, but `icu_i18n_group_translates` has no `locale` column (locale lives in `icu_i18n_translates`). On SQLite the unknown column evaluates to null/false, so the delete is silently skipped when a locale is specified. Fix: join `icu_i18n_translates` before applying the locale filter.

---

## Testing

The package ships with a feature test suite that covers all six repositories against a real SQLite in-memory database using Orchestra Testbench.

```bash
# Install dependencies
composer install

# Run the test suite
./vendor/bin/phpunit --testdox
```

To add your own tests, extend `Tests\Feature\RepositoryTestCase` which configures the in-memory database and runs all package migrations automatically before each test.

---

## Contributing

1. Fork the repository.
2. Create a feature branch.
3. Fix one of the known bugs listed above, or add a new feature.
4. Add tests that cover the change.
5. Run `./vendor/bin/phpunit` and `./vendor/bin/phpstan analyse` — both must pass.
6. Open a pull request.