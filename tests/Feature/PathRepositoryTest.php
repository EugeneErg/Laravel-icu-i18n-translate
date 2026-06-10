<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\ValueObjects\PathId;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadPathRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WritePathRepository;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class PathRepositoryTest extends RepositoryTestCase
{
    // -------------------------------------------------------------------------
    // WritePathRepository::create (root)
    // -------------------------------------------------------------------------

    #[Test]
    public function createRootPathPersistsAndReturnsEntity(): void
    {
        $path = $this->getWritePathRepository()->create('messages');

        $this->assertNotEmpty($path->id->value);
        $this->assertSame('messages', $path->value);
        $this->assertNull($path->parentId);
        $this->assertNull($path->groupId);
    }

    #[Test]
    public function createChildPathStoresParentId(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $child = $this->getWritePathRepository()->create('greeting', $root->id);

        $this->assertNotNull($child->parentId);
        $this->assertSame($root->id->value, $child->parentId->value);
    }

    #[Test]
    public function createPathWithGroupIdStoresGroupId(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');
        $root = $this->getWritePathRepository()->create('messages');
        $path = $this->getWritePathRepository()->create('key', $root->id, $group->id);

        $this->assertNotNull($path->groupId);
        $this->assertSame($group->id->value, $path->groupId->value);
    }

    // -------------------------------------------------------------------------
    // ReadPathRepository::findRoot
    // -------------------------------------------------------------------------

    #[Test]
    public function findRootReturnsPathWithMatchingValueAndNoParent(): void
    {
        $this->getWritePathRepository()->create('messages');

        $found = $this->getReadPathRepository()->findRoot('messages');

        $this->assertNotNull($found);
        $this->assertSame('messages', $found->value);
        $this->assertNull($found->parentId);
    }

    #[Test]
    public function findRootDoesNotReturnChildPaths(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $this->getWritePathRepository()->create('messages', $root->id); // child with same value

        $found = $this->getReadPathRepository()->findRoot('messages');

        $this->assertNotNull($found);
        $this->assertNull($found->parentId);
    }

    #[Test]
    public function findRootReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->getReadPathRepository()->findRoot('nonexistent'));
    }

    // -------------------------------------------------------------------------
    // ReadPathRepository::findChild
    // -------------------------------------------------------------------------

    #[Test]
    public function findChildReturnsMatchingChildPath(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $child = $this->getWritePathRepository()->create('greeting', $root->id);

        $found = $this->getReadPathRepository()->findChild('greeting', $root->id);

        $this->assertNotNull($found);
        $this->assertSame($child->id->value, $found->id->value);
    }

    #[Test]
    public function findChildReturnsNullWhenKeyMissing(): void
    {
        $root = $this->getWritePathRepository()->create('messages');

        $this->assertNull($this->getReadPathRepository()->findChild('missing', $root->id));
    }

    // -------------------------------------------------------------------------
    // ReadPathRepository::listByParentId
    // -------------------------------------------------------------------------

    #[Test]
    public function listByParentIdReturnsAllDirectChildren(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $this->getWritePathRepository()->create('a', $root->id);
        $this->getWritePathRepository()->create('b', $root->id);
        $this->getWritePathRepository()->create('c', $root->id);

        $children = $this->getReadPathRepository()->listByParentId($root->id);

        $this->assertCount(3, $children);
    }

    #[Test]
    public function listByParentIdDoesNotReturnGrandchildren(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $child = $this->getWritePathRepository()->create('section', $root->id);
        $this->getWritePathRepository()->create('deep', $child->id);

        $children = $this->getReadPathRepository()->listByParentId($root->id);

        $this->assertCount(1, $children);
        $this->assertSame('section', $children[0]->value);
    }

    #[Test]
    public function listByParentIdReturnsEmptyArrayForLeaf(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $child = $this->getWritePathRepository()->create('leaf', $root->id);

        $this->assertSame([], $this->getReadPathRepository()->listByParentId($child->id));
    }

    // -------------------------------------------------------------------------
    // ReadPathRepository::listRoot
    // -------------------------------------------------------------------------

    #[Test]
    public function listRootReturnsPaginatedRootPaths(): void
    {
        foreach (range(1, 4) as $i) {
            $this->getWritePathRepository()->create("file{$i}");
        }

        $page1 = $this->getReadPathRepository()->listRoot(0, 2);
        $page2 = $this->getReadPathRepository()->listRoot(2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
    }

    // -------------------------------------------------------------------------
    // ReadPathRepository::findById
    // -------------------------------------------------------------------------

    #[Test]
    public function findByIdReturnsCorrectPath(): void
    {
        $created = $this->getWritePathRepository()->create('messages');

        $found = $this->getReadPathRepository()->findById($created->id);

        $this->assertNotNull($found);
        $this->assertSame($created->id->value, $found->id->value);
    }

    #[Test]
    public function findByIdReturnsNullForUnknownId(): void
    {
        $this->assertNull($this->getReadPathRepository()->findById(new PathId('99999')));
    }

    // -------------------------------------------------------------------------
    // WritePathRepository::delete
    // -------------------------------------------------------------------------

    #[Test]
    public function deleteRemovesPath(): void
    {
        $path = $this->getWritePathRepository()->create('deleteme');

        $this->getWritePathRepository()->delete($path->id);

        $this->assertNull($this->getReadPathRepository()->findById($path->id));
    }

    #[Test]
    public function deleteIsIdempotentForMissingPath(): void
    {
        $this->expectNotToPerformAssertions();
        $this->getWritePathRepository()->delete(new PathId('99999'));
    }

    // -------------------------------------------------------------------------
    // Cascade: deleting a root path cascades to children
    // -------------------------------------------------------------------------

    #[Test]
    public function deletingParentCascadesToChildren(): void
    {
        $root = $this->getWritePathRepository()->create('messages');
        $child = $this->getWritePathRepository()->create('greeting', $root->id);

        // SQLite respects FK cascade because we used foreignId()->cascadeOnDelete()
        $this->getWritePathRepository()->delete($root->id);

        $this->assertNull($this->getReadPathRepository()->findById($root->id));
        $this->assertNull($this->getReadPathRepository()->findById($child->id));
    }

    private function getReadPathRepository(): ReadPathRepository
    {
        return new ReadPathRepository();
    }

    private function getWritePathRepository(): WritePathRepository
    {
        return new WritePathRepository();
    }

    private function getWriteGroupRepository(): WriteGroupRepository
    {
        return new WriteGroupRepository();
    }
}
