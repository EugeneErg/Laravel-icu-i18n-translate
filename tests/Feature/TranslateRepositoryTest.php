<?php

declare(strict_types=1);

namespace Tests\Feature;

use EugeneErg\IcuI18nTranslator\ValueObjects\TranslateId;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Read\ReadTranslateRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteGroupTranslateRepository;
use EugeneErg\LaravelIcuI18nTranslate\Repositories\Write\WriteTranslateRepository;
use PHPUnit\Framework\Attributes\Test;

/**
 * @internal
 */
final class TranslateRepositoryTest extends RepositoryTestCase
{
    // -------------------------------------------------------------------------
    // WriteTranslateRepository::create
    // -------------------------------------------------------------------------

    #[Test]
    public function createPersistsTranslateAndReturnsEntity(): void
    {
        $translate = $this->getWriteTranslateRepository()->create('Bonjour monde', 'fr');

        $this->assertNotEmpty($translate->id->value);
        $this->assertSame('Bonjour monde', $translate->pattern);
        $this->assertSame('fr', $translate->locale);
    }

    // -------------------------------------------------------------------------
    // ReadTranslateRepository::find
    // -------------------------------------------------------------------------

    #[Test]
    public function findReturnsTranslateByPatternHash(): void
    {
        $this->getWriteTranslateRepository()->create('Hello world', 'en');

        $found = $this->getReadTranslateRepository()->find('Hello world');

        $this->assertNotNull($found);
        $this->assertSame('Hello world', $found->pattern);
    }

    #[Test]
    public function findReturnsNullForUnknownPattern(): void
    {
        $this->assertNull($this->getReadTranslateRepository()->find('unknown'));
    }

    #[Test]
    public function findFiltersOnLocaleWhenProvided(): void
    {
        $this->getWriteTranslateRepository()->create('Hello', 'en');

        $notFound = $this->getReadTranslateRepository()->find('Hello', 'fr');
        $found = $this->getReadTranslateRepository()->find('Hello', 'en');

        $this->assertNull($notFound);
        $this->assertNotNull($found);
    }

    // -------------------------------------------------------------------------
    // ReadTranslateRepository::findByGroup
    // -------------------------------------------------------------------------

    #[Test]
    public function findByGroupReturnsTranslateForKey(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');
        $translate = $this->getWriteTranslateRepository()->create('Bonjour', 'fr');
        $this->getWriteGroupTranslateRepository()->create($group->id, $translate->id, 'greeting');

        $found = $this->getReadTranslateRepository()->findByGroup($group->id, 'greeting', 'fr');

        $this->assertNotNull($found);
        $this->assertSame('Bonjour', $found->pattern);
    }

    #[Test]
    public function findByGroupReturnsNullForMissingKey(): void
    {
        $group = $this->getWriteGroupRepository()->create('Hello', '0', null, 'en');

        $result = $this->getReadTranslateRepository()->findByGroup($group->id, 'missing', 'fr');

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // ReadTranslateRepository::groupListByKey
    // -------------------------------------------------------------------------

    #[Test]
    public function groupListByKeyReturnsAllTranslatesKeyedByKey(): void
    {
        $group = $this->getWriteGroupRepository()->create('{n, plural, one {# item} other {# items}}', '0', null, 'en');
        $one = $this->getWriteTranslateRepository()->create('{n} élément', 'fr');
        $other = $this->getWriteTranslateRepository()->create('{n} éléments', 'fr');
        $this->getWriteGroupTranslateRepository()->create($group->id, $one->id, 'one');
        $this->getWriteGroupTranslateRepository()->create($group->id, $other->id, 'other');

        $result = $this->getReadTranslateRepository()->groupListByKey($group->id, 'fr');

        $this->assertArrayHasKey('one', $result);
        $this->assertArrayHasKey('other', $result);
        $this->assertSame('{n} élément', $result['one']->pattern);
        $this->assertSame('{n} éléments', $result['other']->pattern);
    }

    #[Test]
    public function groupListByKeyReturnsEmptyArrayWhenNoTranslates(): void
    {
        $group = $this->getWriteGroupRepository()->create('Test', '0', null, 'en');

        $result = $this->getReadTranslateRepository()->groupListByKey($group->id, 'fr');

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // ReadTranslateRepository::keysListByKey
    // -------------------------------------------------------------------------

    #[Test]
    public function keysListByKeyFiltersOnRequestedKeys(): void
    {
        $group = $this->getWriteGroupRepository()->create('{n, plural, one {# item} other {# items}}', '0', null, 'en');
        $one = $this->getWriteTranslateRepository()->create('# item', 'en');
        $other = $this->getWriteTranslateRepository()->create('# items', 'en');
        $this->getWriteGroupTranslateRepository()->create($group->id, $one->id, 'one');
        $this->getWriteGroupTranslateRepository()->create($group->id, $other->id, 'other');

        $result = $this->getReadTranslateRepository()->keysListByKey($group->id, 'en', ['one']);

        $this->assertArrayHasKey('one', $result);
        $this->assertArrayNotHasKey('other', $result);
    }

    // -------------------------------------------------------------------------
    // WriteTranslateRepository::delete
    // -------------------------------------------------------------------------

    #[Test]
    public function deleteRemovesTranslate(): void
    {
        $translate = $this->getWriteTranslateRepository()->create('To delete', 'en');

        $this->getWriteTranslateRepository()->delete($translate->id);

        $this->assertNull($this->getReadTranslateRepository()->find('To delete', 'en'));
    }

    #[Test]
    public function deleteIsIdempotentForMissingTranslate(): void
    {
        $this->expectNotToPerformAssertions();
        $this->getWriteTranslateRepository()->delete(new TranslateId('99999'));
    }

    private function getReadTranslateRepository(): ReadTranslateRepository
    {
        return new ReadTranslateRepository();
    }

    private function getWriteTranslateRepository(): WriteTranslateRepository
    {
        return new WriteTranslateRepository();
    }

    private function getWriteGroupRepository(): WriteGroupRepository
    {
        return new WriteGroupRepository();
    }

    private function getWriteGroupTranslateRepository(): WriteGroupTranslateRepository
    {
        return new WriteGroupTranslateRepository();
    }
}
