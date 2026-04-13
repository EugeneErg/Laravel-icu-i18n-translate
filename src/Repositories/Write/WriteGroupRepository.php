<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Repositories\Write;

use EugeneErg\IcuI18nTranslator\Entities\Group;
use EugeneErg\IcuI18nTranslator\Repositories\WriteGroupRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\LaravelIcuI18nTranslate\Models\GroupModel;

final readonly class WriteGroupRepository implements WriteGroupRepositoryInterface
{
    public function create(string $originalPattern, string $pattern, ?string $context, string $locale): Group
    {
        $hash = md5((string) json_encode(['pattern' => $originalPattern, 'context' => $context]));
        $result = GroupModel::query()->create([
            'hash' => $hash,
            'pattern' => $pattern,
            'context' => $context,
            'locale' => $locale,
            'original_pattern' => $originalPattern,
        ]);

        return $this->makeGroup($result);
    }

    public function delete(GroupId $id): void
    {
        GroupModel::query()->where('id', '=', $id->value)->delete();
    }

    private function makeGroup(GroupModel $model): Group
    {
        return new Group(
            id: new GroupId((string) $model->id),
            originalPattern: $model->original_pattern,
            pattern: $model->pattern,
            locale: $model->locale,
            context: $model->context,
        );
    }
}