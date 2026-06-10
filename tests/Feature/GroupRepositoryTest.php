<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadGroupRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupRepository;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class GroupRepositoryTest extends RepositoryTestCase
{
    // -------------------------------------------------------------------------
    // WriteGroupRepository::create
    // -------------------------------------------------------------------------

    #[Test]
    public function createPersistsGroupAndReturnsEntity(): void
    {
        $group = $this->getWriteGroupRepository()->create(
            originalPattern: 'Hello world',
            pattern: '0',
            context: 'greeting',
            locale: 'en',
        );

        $this->assertNotEmpty($group->id->value);
        $this->assertSame('Hello world', $group->originalPattern);
        $this->assertSame('0', $group->pattern);
        $this->assertSame('greeting', $group->context);
        $this->assertSame('en', $group->locale);
    }

    #[Test]
    public function createAcceptsNullContext(): void
    {
        $group = $this->getWriteGroupRepository()->create(
            originalPattern: 'Bye',
            pattern: '0',
            context: null,
            locale: 'en',
        );

        $this->assertNull($group->context);
    }

    // -------------------------------------------------------------------------
    // ReadGroupRepository::findByPattern
    // -------------------------------------------------------------------------

    #[Test]
    public function findByPatternReturnsGroupWhenHashMatches(): void
    {
        $this->getWriteGroupRepository()->create('Hello world', '0', null, 'en');

        $found = $this->getReadGroupRepository()->findByPattern('Hello world', null);

        $this->assertNotNull($found);
        $this->assertSame('Hello world', $found->originalPattern);
    }

    #[Test]
    public function findByPatternReturnsNullWhenNotFound(): void
    {
        $result = $this->getReadGroupRepository()->findByPattern('Does not exist', null);

        $this->assertNull($result);
    }

    #[Test]
    public function findByPatternFiltersOnLocale(): void
    {
        $this->getWriteGroupRepository()->create('Hello world', '0', null, 'en');

        $notFound = $this->getReadGroupRepository()->findByPattern('Hello world', null, 'fr');
        $found = $this->getReadGroupRepository()->findByPattern('Hello world', null, 'en');

        $this->assertNull($notFound);
        $this->assertNotNull($found);
    }

    #[Test]
    public function findByPatternTreatsContextAsPartOfHash(): void
    {
        $this->getWriteGroupRepository()->create('Hello', '0', 'ctx-a', 'en');
        $this->getWriteGroupRepository()->create('Hello', '0', 'ctx-b', 'en');

        $a = $this->getReadGroupRepository()->findByPattern('Hello', 'ctx-a');
        $b = $this->getReadGroupRepository()->findByPattern('Hello', 'ctx-b');

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotSame($a->id->value, $b->id->value);
    }

    // -------------------------------------------------------------------------
    // ReadGroupRepository::find
    // -------------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsCorrectGroup(): void
    {
        $created = $this->getWriteGroupRepository()->create('Test', '0', null, 'en');

        $found = $this->getReadGroupRepository()->find($created->id);

        $this->assertNotNull($found);
        $this->assertSame($created->id->value, $found->id->value);
    }

    #[Test]
    public function findByIdReturnsNullForUnknownId(): void
    {
        $result = $this->getReadGroupRepository()->find(new GroupId('99999'));

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // ReadGroupRepository::list
    // -------------------------------------------------------------------------

    #[Test]
    public function listReturnsPaginatedGroups(): void
    {
        foreach (range(1, 5) as $i) {
            $this->getWriteGroupRepository()->create("Pattern {$i}", '0', null, 'en');
        }

        $page1 = $this->getReadGroupRepository()->list(offset: 0, limit: 2);
        $page2 = $this->getReadGroupRepository()->list(offset: 2, limit: 2);
        $page3 = $this->getReadGroupRepository()->list(offset: 4, limit: 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertCount(1, $page3);
    }

    #[Test]
    public function listReturnsEmptyArrayWhenNoGroups(): void
    {
        $this->assertSame([], $this->getReadGroupRepository()->list(0, 10));
    }

    // -------------------------------------------------------------------------
    // WriteGroupRepository::delete
    // -------------------------------------------------------------------------

    #[Test]
    public function deleteRemovesGroup(): void
    {
        $group = $this->getWriteGroupRepository()->create('To delete', '0', null, 'en');

        $this->getWriteGroupRepository()->delete($group->id);

        $this->assertNull($this->getReadGroupRepository()->find($group->id));
    }

    #[Test]
    public function deleteIsIdempotentForMissingGroup(): void
    {
        $this->expectNotToPerformAssertions();
        // Should not throw
        $this->getWriteGroupRepository()->delete(new GroupId('99999'));
    }

    private function getReadGroupRepository(): ReadGroupRepository
    {
        return new ReadGroupRepository();
    }

    private function getWriteGroupRepository(): WriteGroupRepository
    {
        return new WriteGroupRepository();
    }
}
