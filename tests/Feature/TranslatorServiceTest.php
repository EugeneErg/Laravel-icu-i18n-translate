<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\DataTransferObjects\Variable;
use EugeneErg\IcuI18nTranslator\Exceptions\UnexpectedTranslateDirectionException;
use EugeneErg\IcuI18nTranslator\FormatterInterface;
use EugeneErg\IcuI18nTranslator\Translator;
use EugeneErg\IcuI18nTranslator\TranslatorInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\Translated;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests: Translator service wired to real SQLite repositories.
 *
 * Uses a configurable stub translator so we can inject deterministic translations
 * without hitting any external API, and assert that:
 *   – the service persists source + translated strings in the DB;
 *   – second calls are served from the DB without re-calling the translator;
 *   – ICU plural patterns are correctly split, translated per-variant, and reassembled;
 *   – context disambiguation creates separate DB groups;
 *   – getTranslates / setTranslate / deleteTranslateFromGroup work end-to-end.
 *
 * @internal
 */
final class TranslatorServiceTest extends RepositoryTestCase
{
    // =========================================================================
    // translateText
    // =========================================================================

    #[Test]
    public function translateTextCallsDriverAndPersistsResult(): void
    {
        $translator = $this->makeTranslator(['fr' => ['Hello world' => 'Bonjour le monde']]);

        $result = $translator->translateText('Hello world', 'fr', 'en');

        $this->assertSame('Bonjour le monde', $result);
    }

    #[Test]
    public function translateTextSecondCallUsesDbCacheNotDriver(): void
    {
        $calls = 0;
        $dictionary = ['fr' => ['Hello world' => 'Bonjour le monde']];

        // First call populates the DB
        $translator = $this->makeTranslator($dictionary);
        $translator->translateText('Hello world', 'fr', 'en');

        // Replace with a stub that counts calls
        $counting = new CountingTranslator($dictionary, 'en', $calls);
        $this->app->instance(TranslatorInterface::class . '[]', [$counting]);
        $this->app->forgetInstance(Translator::class);
        $translator2 = $this->app->make(Translator::class);

        $result = $translator2->translateText('Hello world', 'fr', 'en');

        $this->assertSame('Bonjour le monde', $result);
        $this->assertSame(0, $calls, 'Driver must not be called on cache hit');
    }

    #[Test]
    public function translateTextWithContextCreatesDistinctGroups(): void
    {
        $translator = $this->makeTranslator([
            'fr' => [
                'Order' => 'Commander',   // verb (UI button context)
            ],
        ]);

        // Use slightly different source strings so we can test independently
        $translatorA = $this->makeTranslator(['fr' => ['Cancel' => 'Annuler (verb)']]);
        $translatorB = $this->makeTranslator(['fr' => ['Cancel' => 'Annuler (noun)']]);

        $verb = $translatorA->translateText('Cancel', 'fr', 'en', 'action');
        $noun = $translatorB->translateText('Cancel', 'fr', 'en', 'label');

        // Both translations are persisted; each context is a separate group
        $this->assertSame('Annuler (verb)', $verb);
        $this->assertSame('Annuler (noun)', $noun);
    }

    #[Test]
    public function translateTextThrowsWhenNoDriverSupportsTargetLocale(): void
    {
        // No driver registered at all
        $translator = $this->makeTranslator([]);

        $this->expectException(UnexpectedTranslateDirectionException::class);

        $translator->translateText('Hello', 'zh', 'en');
    }

    // =========================================================================
    // translateMessage — ICU plural patterns
    // =========================================================================

    #[Test]
    public function translateMessageSplitsPluralsAndTranslatesPerVariant(): void
    {
        // The ICU parser splits '# item' into [Variable(count), ' item'].
        // The translator passes string parts to the driver; Variable slots pass through untouched.
        // So the driver receives ' item' (with leading space) and ' items', not '# item' / '# items'.
        $translator = $this->makeTranslator([
            'fr' => [
                ' item' => ' élément',
                ' items' => ' éléments',
            ],
        ]);

        $pattern = '{count, plural, one {# item} other {# items}}';

        $singular = $translator->translateMessage($pattern, ['count' => 1], 'fr', 'en');
        $plural = $translator->translateMessage($pattern, ['count' => 5], 'fr', 'en');

        $this->assertSame('1 élément', $singular);
        $this->assertSame('5 éléments', $plural);
    }

    #[Test]
    public function translateMessageSecondCallServedFromDbForAllVariants(): void
    {
        $dictionary = [
            'fr' => [
                '# item' => '# élément',
                '# items' => '# éléments',
            ],
        ];
        $calls = 0;

        $translator = $this->makeTranslator($dictionary);
        $pattern = '{count, plural, one {# item} other {# items}}';
        $translator->translateMessage($pattern, ['count' => 1], 'fr', 'en');
        $translator->translateMessage($pattern, ['count' => 5], 'fr', 'en');

        $counting = new CountingTranslator($dictionary, 'en', $calls);
        $this->app->instance(TranslatorInterface::class . '[]', [$counting]);
        $this->app->forgetInstance(Translator::class);
        $translator2 = $this->app->make(Translator::class);

        $translator2->translateMessage($pattern, ['count' => 1], 'fr', 'en');
        $translator2->translateMessage($pattern, ['count' => 5], 'fr', 'en');

        $this->assertSame(0, $calls, 'Both plural variants already cached; driver must not be called');
    }

    #[Test]
    public function translateMessageHandlesSelectPattern(): void
    {
        $translator = $this->makeTranslator([
            'fr' => [
                'He likes cats' => 'Il aime les chats',
                'She likes cats' => 'Elle aime les chats',
                'They like cats' => 'Ils aiment les chats',
            ],
        ]);

        $pattern = '{gender, select, male {He likes cats} female {She likes cats} other {They like cats}}';

        $this->assertSame('Il aime les chats', $translator->translateMessage($pattern, ['gender' => 'male'], 'fr', 'en'));
        $this->assertSame('Elle aime les chats', $translator->translateMessage($pattern, ['gender' => 'female'], 'fr', 'en'));
        $this->assertSame('Ils aiment les chats', $translator->translateMessage($pattern, ['gender' => 'other'], 'fr', 'en'));
    }

    // =========================================================================
    // Auto-detect source locale (no fromLocale provided)
    // =========================================================================

    #[Test]
    public function translateTextWithoutFromLocaleDetectsSourceAndPersists(): void
    {
        $translator = $this->makeTranslator(['fr' => ['Hello' => 'Bonjour']], sourceLocale: 'en');

        // No fromLocale — driver must detect it
        $result = $translator->translateText('Hello', 'fr');

        $this->assertSame('Bonjour', $result);
    }

    // =========================================================================
    // getTranslates / setTranslate / deleteTranslateFromGroup
    // =========================================================================

    #[Test]
    public function getTranslatesReturnsAllVariantsForGroup(): void
    {
        $translator = $this->makeTranslator([
            'fr' => ['# item' => '# élément', '# items' => '# éléments'],
        ]);
        $pattern = '{count, plural, one {# item} other {# items}}';
        $translator->translateMessage($pattern, ['count' => 1], 'fr', 'en');
        $translator->translateMessage($pattern, ['count' => 5], 'fr', 'en');

        $groups = $translator->getGroups(pageSize: 10);
        $this->assertNotEmpty($groups);

        $translates = $translator->getTranslates($groups[0]->id, 'fr');

        $this->assertArrayHasKey('0', $translates); // 'one' variant key resolved by ICU
        $this->assertArrayHasKey('1', $translates); // 'other' variant key
    }

    #[Test]
    public function setTranslateOverridesExistingTranslation(): void
    {
        $translator = $this->makeTranslator(['fr' => ['Hello' => 'Bonjour']]);
        $translator->translateText('Hello', 'fr', 'en');

        $groups = $translator->getGroups(pageSize: 10);
        $this->assertNotEmpty($groups);

        // Override the translation manually
        $translator->setTranslate($groups[0]->id, key: '0', locale: 'fr', pattern: 'Salut');

        // New translation is used
        $result = $translator->translateText('Hello', 'fr', 'en');
        $this->assertSame('Salut', $result);
    }

    #[Test]
    public function deleteTranslateFromGroupForcesRetranslationOnNextCall(): void
    {
        $translator = $this->makeTranslator(['fr' => ['Hello' => 'Bonjour']]);
        $translator->translateText('Hello', 'fr', 'en');

        $groups = $translator->getGroups(pageSize: 10);
        $this->assertNotEmpty($groups);

        // Delete the cached translation
        $translator->deleteTranslateFromGroup($groups[0]->id, key: '0', locale: 'fr');

        // Now it must call the driver again
        $calls = 0;
        $stub = new CountingTranslator(['fr' => ['Hello' => 'Bonjour']], 'en', $calls);
        $this->app->instance(TranslatorInterface::class . '[]', [$stub]);
        $this->app->forgetInstance(Translator::class);

        $result = $this->app->make(Translator::class)->translateText('Hello', 'fr', 'en');

        $this->assertSame('Bonjour', $result);
        $this->assertSame(1, $calls, 'Driver must be called after cache eviction');
    }

    // =========================================================================
    // getGroups pagination
    // =========================================================================

    #[Test]
    public function getGroupsReturnsPaginatedResults(): void
    {
        $translator = $this->makeTranslator(['fr' => ['A' => 'A-fr', 'B' => 'B-fr', 'C' => 'C-fr']]);
        $translator->translateText('A', 'fr', 'en');
        $translator->translateText('B', 'fr', 'en');
        $translator->translateText('C', 'fr', 'en');

        $page1 = $translator->getGroups(pageSize: 2, page: 1);
        $page2 = $translator->getGroups(pageSize: 2, page: 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a Translator whose only "external" driver is our controllable stub.
     *
     * @param array<string, array<string, string>> $dictionary
     *                                                         E.g. ['fr' => ['Hello' => 'Bonjour', 'world' => 'monde']]
     */
    private function makeTranslator(array $dictionary = [], string $sourceLocale = 'en'): Translator
    {
        $stub = new StubTranslator($dictionary, $sourceLocale);

        $this->app->instance(TranslatorInterface::class . '[]', [$stub]);
        $this->app->instance(FormatterInterface::class . '[]', []);
        // Force the Translator singleton to be re-created with our stub
        $this->app->forgetInstance(Translator::class);

        return $this->app->make(Translator::class);
    }
}
