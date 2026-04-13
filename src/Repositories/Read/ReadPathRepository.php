<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Repositories\Read;

use EugeneErg\IcuI18nTranslator\Entities\Path;
use EugeneErg\IcuI18nTranslator\Repositories\ReadPathRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\IcuI18nTranslator\ValueObjects\PathId;
use EugeneErg\LaravelIcuI18nTranslate\Models\PathModel;

final readonly class ReadPathRepository implements ReadPathRepositoryInterface
{
    public function findRoot(string $value): ?Path
    {
        $result = PathModel::query()
            ->where('value', '=', $value)
            ->whereNull('parent_id')
            ->first();

        return $result === null ? null : $this->makePath($result);
    }

    public function listByParentId(PathId $parentId): array
    {
        return array_map([$this, 'makePath'], PathModel::query()
            ->where('parent_id', '=', (int) $parentId->value)
            ->get()
            ->all());
    }

    public function findChild(string $value, PathId $parentId): ?Path
    {
        $result = PathModel::query()
            ->where('value', '=', $value)
            ->where('parent_id', '=', $parentId->value)
            ->first();

        return $result === null ? null : $this->makePath($result);
    }

    public function listRoot(int $offset, int $limit): array
    {
        return array_map([$this, 'makePath'], PathModel::query()->limit($limit)->offset($offset)->get()->all());
    }

    public function findById(PathId $id): ?Path
    {
        $result = PathModel::query()->where('id', '=', $id->value)->first();

        return $result === null ? null : $this->makePath($result);
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