<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\DataTransferObjects\Variable;
use EugeneErg\IcuI18nTranslator\TranslatorInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\Translated;

// =============================================================================
// Test doubles
// =============================================================================

/**
 * A deterministic translator that looks up translations in a fixed dictionary.
 * Handles Variable pass-through so ICU placeholder slots are preserved.
 */
final class StubTranslator implements TranslatorInterface
{
    /**
     * @param array<string, array<string, string>> $dictionary
     */
    public function __construct(
        private readonly array $dictionary,
        private readonly string $sourceLocale = 'en',
    ) {}

    public function canTranslate(string $toLocale, string|null $fromLocale = null): bool
    {
        return isset($this->dictionary[$toLocale]);
    }

    public function translate(array $pattern, string $fromLocale, string $toLocale, string|null $context = null): array
    {
        return $this->doTranslate($pattern, $toLocale);
    }

    public function translateWithDetect(array $pattern, string $toLocale, string|null $context = null): Translated
    {
        return new Translated($this->sourceLocale, $this->doTranslate($pattern, $toLocale));
    }

    /**
     * @param array<string|Variable> $pattern @return array<string|Variable>
     */
    private function doTranslate(array $pattern, string $toLocale): array
    {
        $dict = $this->dictionary[$toLocale] ?? [];

        return array_map(static function (string|Variable $part) use ($dict): string|Variable {
            if ($part instanceof Variable) {
                return $part;
            }

            return $dict[$part] ?? $part;
        }, $pattern);
    }
}
