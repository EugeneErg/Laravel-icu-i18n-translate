<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadTranslateRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupTranslateRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteTranslateRepository;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class GroupTranslateRepositoryTest extends RepositoryTestCase
{
    // -------------------------------------------------------------------------
    // WriteGroupTranslateRepository::create
    // -------------------------------------------------------------------------

    #[Test]
    public function createPersistsGroupTranslateLinkWithoutSourceId(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');
        $translate = $this->getWriteTranslateRepository()->create('Bonjour', 'fr');

        $link = $this->getWriteGroupTranslateRepository()->create($group->id, $translate->id, 'greeting');

        $this->assertSame($group->id->value, $link->groupId->value);
        $this->assertSame($translate->id->value, $link->translateId->value);
        $this->assertSame('greeting', $link->key);
        $this->assertNull($link->sourceId);
    }

    #[Test]
    public function createPersistsGroupTranslateWithSourceId(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');
        $source = $this->getWriteTranslateRepository()->create('Hello', 'en');
        $translate = $this->getWriteTranslateRepository()->create('Bonjour', 'fr');

        $link = $this->getWriteGroupTranslateRepository()->create($group->id, $translate->id, 'greeting', $source->id);

        $this->assertNotNull($link->sourceId);
        $this->assertSame($source->id->value, $link->sourceId->value);
    }

    // -------------------------------------------------------------------------
    // WriteGroupTranslateRepository::deleteByGroupId
    // -------------------------------------------------------------------------

    #[Test]
    public function deleteByGroupIdRemovesAllLinksForGroup(): void
    {
        $group = $this->getWriteGroupRepository()->create('Plural', '0', null, 'en');
        $t1 = $this->getWriteTranslateRepository()->create('# item', 'en');
        $t2 = $this->getWriteTranslateRepository()->create('# items', 'en');
        $this->getWriteGroupTranslateRepository()->create($group->id, $t1->id, 'one');
        $this->getWriteGroupTranslateRepository()->create($group->id, $t2->id, 'other');

        $this->getWriteGroupTranslateRepository()->deleteByGroupId($group->id);

        $this->assertNull($this->getReadTranslateRepository()->findByGroup($group->id, 'one', 'en'));
        $this->assertNull($this->getReadTranslateRepository()->findByGroup($group->id, 'other', 'en'));
    }

    #[Test]
    public function deleteByGroupIdWithKeyOnlyRemovesMatchingKey(): void
    {
        $group = $this->getWriteGroupRepository()->create('Plural', '0', null, 'en');
        $t1 = $this->getWriteTranslateRepository()->create('# item', 'en');
        $t2 = $this->getWriteTranslateRepository()->create('# items', 'en');
        $this->getWriteGroupTranslateRepository()->create($group->id, $t1->id, 'one');
        $this->getWriteGroupTranslateRepository()->create($group->id, $t2->id, 'other');

        $this->getWriteGroupTranslateRepository()->deleteByGroupId($group->id, 'one');

        $this->assertNull($this->getReadTranslateRepository()->findByGroup($group->id, 'one', 'en'));
        $this->assertNotNull($this->getReadTranslateRepository()->findByGroup($group->id, 'other', 'en'));
    }

    #[Test]
    public function deleteByGroupIdWithLocaleOnlyRemovesLinksForThatLocale(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');
        $en = $this->getWriteTranslateRepository()->create('Hello', 'en');
        $fr = $this->getWriteTranslateRepository()->create('Bonjour', 'fr');
        $this->getWriteGroupTranslateRepository()->create($group->id, $en->id, 'greeting');
        $this->getWriteGroupTranslateRepository()->create($group->id, $fr->id, 'greeting');

        $this->getWriteGroupTranslateRepository()->deleteByGroupId($group->id, null, 'fr');

        $this->assertNull($this->getReadTranslateRepository()->findByGroup($group->id, 'greeting', 'fr'));
        $this->assertNotNull($this->getReadTranslateRepository()->findByGroup($group->id, 'greeting', 'en'));
    }

    #[Test]
    public function deleteByGroupIdWithKeyAndLocaleRemovesOnlyMatch(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');
        $t = $this->getWriteTranslateRepository()->create('Bonjour', 'fr');
        $this->getWriteGroupTranslateRepository()->create($group->id, $t->id, 'greeting');

        $this->getWriteGroupTranslateRepository()->deleteByGroupId($group->id, 'greeting', 'fr');

        $this->assertNull($this->getReadTranslateRepository()->findByGroup($group->id, 'greeting', 'fr'));
    }

    #[Test]
    public function deleteByGroupIdIsIdempotentWhenNothingMatches(): void
    {
        $this->expectNotToPerformAssertions();

        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');

        $this->getWriteGroupTranslateRepository()->deleteByGroupId($group->id);
    }

    private function getWriteGroupRepository(): WriteGroupRepository
    {
        return new WriteGroupRepository();
    }

    private function getWriteTranslateRepository(): WriteTranslateRepository
    {
        return new WriteTranslateRepository();
    }

    private function getWriteGroupTranslateRepository(): WriteGroupTranslateRepository
    {
        return new WriteGroupTranslateRepository();
    }

    private function getReadTranslateRepository(): ReadTranslateRepository
    {
        return new ReadTranslateRepository();
    }
}
