<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Repositories\Write;

use EugeneErg\IcuI18nTranslator\Entities\Path;
use EugeneErg\IcuI18nTranslator\Repositories\WritePathRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\IcuI18nTranslator\ValueObjects\PathId;
use EugeneErg\LaravelIcuI18nTranslate\Models\PathModel;

final readonly class WritePathRepository implements WritePathRepositoryInterface
{
    public function create(string $value, ?PathId $parentId = null, ?GroupId $groupId = null,): Path
    {
        $result = PathModel::query()->create([
            'value' => $value,
            'parent_id' => $parentId?->value,
            'group_id' => $groupId?->value,
        ]);

        return $this->makePath($result);
    }

    public function delete(PathId $id): void
    {
        PathModel::query()->where('id', '=', $id->value)->delete();
    }

    private function makePath(PathModel $model): Path
    {
        return new Path(
            id: new PathId((string) $model->id),
            value: $model->value,
            parentId: $model->parent_id === null ? null : new PathId((string) $model->parent_id),
            groupId: $model->group_id === null ? null : new GroupId((string) $model->group_id),
        );
    }
}