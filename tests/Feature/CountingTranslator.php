<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\TranslatorInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\Translated;

/**
 * A translator that wraps StubTranslator but increments a counter on each translate call,
 * so tests can verify the driver is NOT called when the DB cache is warm.
 */
final class CountingTranslator implements TranslatorInterface
{
    private readonly StubTranslator $inner;

    /**
     * @param array<string, array<string, string>> $dictionary
     */
    public function __construct(
        array $dictionary,
        string $sourceLocale,
        private int &$callCount,
    ) {
        $this->inner = new StubTranslator($dictionary, $sourceLocale);
    }

    public function canTranslate(string $toLocale, string|null $fromLocale = null): bool
    {
        return $this->inner->canTranslate($toLocale, $fromLocale);
    }

    public function translate(array $pattern, string $fromLocale, string $toLocale, string|null $context = null): array
    {
        ++$this->callCount;

        return $this->inner->translate($pattern, $fromLocale, $toLocale, $context);
    }

    public function translateWithDetect(array $pattern, string $toLocale, string|null $context = null): Translated
    {
        ++$this->callCount;

        return $this->inner->translateWithDetect($pattern, $toLocale, $context);
    }
}
