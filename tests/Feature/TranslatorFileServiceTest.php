<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\DataTransferObjects\FilePathContainer;
use EugeneErg\IcuI18nTranslator\Exceptions\FileNotFoundException;
use EugeneErg\IcuI18nTranslator\Exceptions\FormatNotFoundException;
use EugeneErg\IcuI18nTranslator\FormatterInterface;
use EugeneErg\IcuI18nTranslator\Translator;
use EugeneErg\IcuI18nTranslator\TranslatorInterface;
use EugeneErg\ICUMessageFormatParser\Parser;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests: file-tree methods of Translator.
 *
 * Covers: addFile, getFile, findFile, getFiles, getFileBranch,
 *         createEmptyFile, addFilePath, deleteFileBranch, deleteTranslate.
 *
 * Uses a StubFormatter that stores the last formatted/parsed FilePathContainer
 * so tests can verify round-trip fidelity without a real JSON/YAML parser.
 *
 * @internal
 */
final class TranslatorFileServiceTest extends RepositoryTestCase
{
    // =========================================================================
    // addFile / getFile
    // =========================================================================

    #[Test]
    public function addFileParsesContentAndPersistsGroupsForEachKey(): void
    {
        $formatter = new StubFormatter();
        $formatter->nextParse = new FilePathContainer([
            'greeting' => 'Hello world',
            'farewell' => 'Goodbye',
        ]);

        $translator = $this->makeTranslator(formatter: $formatter);
        $translator->addFile('stub', 'messages', '{}', 'en');

        // Both keys must appear as root path → child nodes
        $root = $translator->findFile('messages');
        $this->assertNotNull($root);

        $children = $translator->getFileBranch($root->id);
        $keys = array_map(static fn ($p) => $p->value, $children);
        $this->assertContains('greeting', $keys);
        $this->assertContains('farewell', $keys);
    }

    #[Test]
    public function addFileHandlesNestedFilePathContainer(): void
    {
        $formatter = new StubFormatter();
        $formatter->nextParse = new FilePathContainer([
            'auth' => new FilePathContainer([
                'login' => 'Log in',
                'logout' => 'Log out',
            ]),
        ]);

        $translator = $this->makeTranslator(formatter: $formatter);
        $translator->addFile('stub', 'messages', '{}', 'en');

        $root = $translator->findFile('messages');
        $children = $translator->getFileBranch($root->id);
        $this->assertCount(1, $children);
        $this->assertSame('auth', $children[0]->value);

        $nested = $translator->getFileBranch($children[0]->id);
        $nestedKeys = array_map(static fn ($p) => $p->value, $nested);
        $this->assertContains('login', $nestedKeys);
        $this->assertContains('logout', $nestedKeys);
    }

    #[Test]
    public function addFileHandlesIcuPatternAsTypesObject(): void
    {
        $parser = $this->makeParser();
        $formatter = new StubFormatter();
        $formatter->nextParse = new FilePathContainer([
            'items' => $parser->parse('{count, plural, one {# item} other {# items}}'),
        ]);

        $translator = $this->makeTranslator(formatter: $formatter);
        $translator->addFile('stub', 'messages', '{}', 'en');

        $root = $translator->findFile('messages');
        $this->assertNotNull($root);
        $children = $translator->getFileBranch($root->id);
        $this->assertCount(1, $children);
        $this->assertSame('items', $children[0]->value);
        // The path must be linked to a group
        $this->assertNotNull($children[0]->groupId);
    }

    #[Test]
    public function getFileCallsFormatterWithTranslatedContent(): void
    {
        $formatter = new StubFormatter();
        $formatter->nextParse = new FilePathContainer(['greeting' => 'Hello']);

        $translator = $this->makeTranslator(
            dictionary: ['fr' => ['Hello' => 'Bonjour']],
            formatter: $formatter,
        );
        $translator->addFile('stub', 'messages', '{}', 'en');

        // Pre-translate so the FR variant is cached
        $translator->translateText('Hello', 'fr', 'en');

        $formatted = $translator->getFile('stub', 'messages', 'fr');

        // StubFormatter::format just JSON-encodes the container's leaf patterns
        $this->assertNotEmpty($formatted);
        $this->assertStringContainsString('Bonjour', $formatted);
    }

    #[Test]
    public function getFileThrowsFormatNotFoundWhenFormatUnknown(): void
    {
        $translator = $this->makeTranslator();

        $this->expectException(FormatNotFoundException::class);
        $translator->getFile('nonexistent_format', 'messages', 'en');
    }

    #[Test]
    public function addFileThrowsFormatNotFoundWhenFormatUnknown(): void
    {
        $translator = $this->makeTranslator();

        $this->expectException(FormatNotFoundException::class);
        $translator->addFile('nonexistent_format', 'messages', '{}', 'en');
    }

    #[Test]
    public function getFileThrowsFileNotFoundWhenFileDoesNotExist(): void
    {
        $translator = $this->makeTranslator();

        $this->expectException(FileNotFoundException::class);
        $translator->getFile('stub', 'nonexistent', 'en');
    }

    #[Test]
    public function addFileCalledTwiceForSameNameUpdatesExistingPaths(): void
    {
        $formatter = new StubFormatter();
        $formatter->nextParse = new FilePathContainer(['greeting' => 'Hello']);
        $translator = $this->makeTranslator(formatter: $formatter);
        $translator->addFile('stub', 'messages', '{}', 'en');

        // Call again with same file name — should not create a duplicate root
        $formatter->nextParse = new FilePathContainer(['greeting' => 'Hello', 'farewell' => 'Goodbye']);
        $translator->addFile('stub', 'messages', '{}', 'en');

        $files = $translator->getFiles(pageSize: 50);
        $roots = array_filter($files, static fn ($f) => $f->value === 'messages');
        $this->assertCount(1, $roots, 'Duplicate root path must not be created');
    }

    // =========================================================================
    // findFile
    // =========================================================================

    #[Test]
    public function findFileReturnsRootPathByName(): void
    {
        $formatter = new StubFormatter();
        $formatter->nextParse = new FilePathContainer(['k' => 'v']);
        $translator = $this->makeTranslator(formatter: $formatter);
        $translator->addFile('stub', 'validation', '{}', 'en');

        $path = $translator->findFile('validation');

        $this->assertNotNull($path);
        $this->assertSame('validation', $path->value);
        $this->assertNull($path->parentId);
    }

    #[Test]
    public function findFileReturnsNullWhenFileNotRegistered(): void
    {
        $translator = $this->makeTranslator();

        $this->assertNull($translator->findFile('does_not_exist'));
    }

    // =========================================================================
    // getFiles (pagination)
    // =========================================================================

    #[Test]
    public function getFilesReturnsPaginatedRootPaths(): void
    {
        $translator = $this->makeTranslator();
        $translator->createEmptyFile('a');
        $translator->createEmptyFile('b');
        $translator->createEmptyFile('c');

        $page1 = $translator->getFiles(pageSize: 2, page: 1);
        $page2 = $translator->getFiles(pageSize: 2, page: 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }

    #[Test]
    public function getFilesReturnsEmptyArrayWhenNoFiles(): void
    {
        $translator = $this->makeTranslator();

        $this->assertSame([], $translator->getFiles(pageSize: 10));
    }

    // =========================================================================
    // createEmptyFile / addFilePath / getFileBranch
    // =========================================================================

    #[Test]
    public function createEmptyFileCreatesRootPathAndReturnsId(): void
    {
        $translator = $this->makeTranslator();

        $pathId = $translator->createEmptyFile('routes');

        $found = $translator->findFile('routes');
        $this->assertNotNull($found);
        $this->assertSame($pathId->value, $found->id->value);
    }

    #[Test]
    public function addFilePathCreatesChildUnderParent(): void
    {
        $translator = $this->makeTranslator();
        $parentId = $translator->createEmptyFile('lang');

        $childId = $translator->addFilePath($parentId, 'en');

        $children = $translator->getFileBranch($parentId);
        $this->assertCount(1, $children);
        $this->assertSame('en', $children[0]->value);
        $this->assertSame($childId->value, $children[0]->id->value);
    }

    #[Test]
    public function getFileBranchReturnsOnlyDirectChildren(): void
    {
        $translator = $this->makeTranslator();
        $root = $translator->createEmptyFile('lang');
        $child = $translator->addFilePath($root, 'en');
        $translator->addFilePath($child, 'deep'); // grandchild

        $branch = $translator->getFileBranch($root);

        $this->assertCount(1, $branch);
        $this->assertSame('en', $branch[0]->value);
    }

    #[Test]
    public function getFileBranchReturnsEmptyArrayForLeafNode(): void
    {
        $translator = $this->makeTranslator();
        $root = $translator->createEmptyFile('lang');
        $leaf = $translator->addFilePath($root, 'en');

        $this->assertSame([], $translator->getFileBranch($leaf));
    }

    // =========================================================================
    // deleteFileBranch
    // =========================================================================

    #[Test]
    public function deleteFileBranchRemovesRootAndAllDescendants(): void
    {
        $translator = $this->makeTranslator();
        $root = $translator->createEmptyFile('lang');
        $child = $translator->addFilePath($root, 'en');
        $translator->addFilePath($child, 'deep');

        $translator->deleteFileBranch($root);

        $this->assertNull($translator->findFile('lang'));
        $this->assertSame([], $translator->getFiles(pageSize: 50));
    }

    #[Test]
    public function deleteFileBranchIsNoOpForNonExistentPath(): void
    {
        $translator = $this->makeTranslator();
        $root = $translator->createEmptyFile('lang');
        $translator->deleteFileBranch($root); // delete once

        // delete again — should not throw
        $translator->deleteFileBranch($root);
        $this->assertTrue(true);
    }

    #[Test]
    public function deleteFileBranchOnlyRemovesTargetSubtree(): void
    {
        $translator = $this->makeTranslator();
        $rootA = $translator->createEmptyFile('fileA');
        $rootB = $translator->createEmptyFile('fileB');

        $translator->deleteFileBranch($rootA);

        $this->assertNull($translator->findFile('fileA'));
        $this->assertNotNull($translator->findFile('fileB'));
    }

    // =========================================================================
    // deleteTranslate
    // =========================================================================

    #[Test]
    public function deleteTranslateRemovesTranslateById(): void
    {
        $translator = $this->makeTranslator(['fr' => ['Hello' => 'Bonjour']]);
        $translator->translateText('Hello', 'fr', 'en');

        $groups = $translator->getGroups(pageSize: 10);
        $translates = $translator->getTranslates($groups[0]->id, 'fr');

        // Pick the translate id from the first variant
        $firstKey = (string) array_key_first($translates);
        $translateId = $translates[$firstKey]->pattern; // pattern tells us what was stored

        // We need the actual TranslateId — use getTranslates which returns DataTransferObjects\Translate
        // Retranslate after deleting the cached variant
        $allTranslates = $translator->getTranslates($groups[0]->id, 'fr');
        $this->assertNotEmpty($allTranslates);

        // deleteTranslate is called with a TranslateId; get it via setTranslate round-trip
        $stored = $translator->setTranslate($groups[0]->id, $firstKey, 'fr', 'Bonjour');
        $translator->deleteTranslate($stored->id);

        // After deletion the translate record is gone — the next translateText must re-call the driver
        $calls = 0;
        $stub = new CountingTranslator(['fr' => ['Hello' => 'Bonjour']], 'en', $calls);
        $this->app->instance(TranslatorInterface::class . '[]', [$stub]);
        $this->app->forgetInstance(Translator::class);

        // The group_translate pivot still points to the deleted translate → re-translate is needed
        // (driver call count ≥ 1 proves it wasn't served from cache)
        $this->app->make(Translator::class)->translateText('Hello', 'fr', 'en');
        $this->assertGreaterThanOrEqual(1, $calls);
    }
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTranslator(
        array $dictionary = [],
        string $sourceLocale = 'en',
        StubFormatter|null $formatter = null,
    ): Translator {
        $stub = new StubTranslator($dictionary, $sourceLocale);
        $formatter ??= new StubFormatter();

        $this->app->instance(TranslatorInterface::class . '[]', [$stub]);
        $this->app->instance(FormatterInterface::class . '[]', ['stub' => $formatter]);
        $this->app->forgetInstance(Translator::class);

        return $this->app->make(Translator::class);
    }

    private function makeParser(): Parser
    {
        return $this->app->make(Parser::class);
    }
}
