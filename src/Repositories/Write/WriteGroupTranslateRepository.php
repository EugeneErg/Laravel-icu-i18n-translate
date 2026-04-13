<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Repositories\Write;

use EugeneErg\IcuI18nTranslator\Entities\GroupTranslate;
use EugeneErg\IcuI18nTranslator\Repositories\WriteGroupTranslateRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\IcuI18nTranslator\ValueObjects\TranslateId;
use EugeneErg\LaravelIcuI18nTranslate\Models\GroupTranslateModel;

final readonly class WriteGroupTranslateRepository implements WriteGroupTranslateRepositoryInterface
{
    public function create(
        GroupId $groupId,
        TranslateId $translateId,
        string $key,
        ?TranslateId $sourceId = null,
    ): GroupTranslate {
        $result = GroupTranslateModel::query()->create([
            'group_id' => (int) $groupId->value,
            'translate_id' => (int) $translateId->value,
            'key' => $key,
            'source_id' => $sourceId === null ? null : (int) $sourceId->value,
        ]);

        return $this->makeGroupTranslate($result);
    }

    public function deleteByGroupId(GroupId $groupId, ?string $key = null, ?string $locale = null): void
    {
        $query = GroupTranslateModel::query()->where('group_id', '=', (int) $groupId->value);

        if ($key !== null) {
            $query->where('key', '=', $key);
        }

        if ($locale !== null) {
            $query->where('locale', '=', $locale);
        }

        $query->delete();
    }

    private function makeGroupTranslate(GroupTranslateModel $model): GroupTranslate
    {
        return new GroupTranslate(
            groupId: new GroupId((string) $model->group_id),
            translateId: new TranslateId((string) $model->translate_id),
            key: $model->key,
            sourceId: $model->source_id === null ? null : new TranslateId((string) $model->source_id),
        );
    }
}