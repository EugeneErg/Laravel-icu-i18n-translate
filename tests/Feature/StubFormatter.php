<?php

declare(strict_types=1);

namespace Tests\Feature;

// =============================================================================
// StubFormatter
// =============================================================================
use EugeneErg\IcuI18nTranslator\DataTransferObjects\FilePathContainer;
use EugeneErg\IcuI18nTranslator\FormatterInterface;

use const JSON_UNESCAPED_UNICODE;

/**
 * Formatter stub for testing.
 *
 * parse()  → returns $nextParse (set by the test before calling addFile).
 * format() → JSON-encodes the leaf string values from the container tree,
 *            so getFile() assertions can check for translated strings.
 */
final class StubFormatter implements FormatterInterface
{
    public FilePathContainer|null $nextParse = null;

    public FilePathContainer|null $lastFormatted = null;

    public function parse(string $content): FilePathContainer
    {
        return $this->nextParse ?? new FilePathContainer();
    }

    public function format(FilePathContainer $file): string
    {
        $this->lastFormatted = $file;

        return json_encode($this->flatten($file), JSON_UNESCAPED_UNICODE);
    }

    private function flatten(FilePathContainer $container, string $prefix = ''): array
    {
        $result = [];

        foreach ($container->children as $key => $child) {
            $fullKey = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;

            if ($child instanceof FilePathContainer) {
                $result += $this->flatten($child, $fullKey);
            } else {
                // Types or string — just cast to string
                $result[$fullKey] = (string) $child;
            }
        }

        return $result;
    }
}
