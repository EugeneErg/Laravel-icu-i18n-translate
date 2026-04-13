<?php

declare(strict_types = 1);

namespace EugeneErg\TranslateLaravel\Repositories\Read;

use EugeneErg\IcuI18nTranslator\Entities\Group;
use EugeneErg\IcuI18nTranslator\Repositories\ReadGroupRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\TranslateLaravel\Models\GroupModel;

final readonly class ReadGroupRepository implements ReadGroupRepositoryInterface
{
    public function findByPattern(string $originalPattern, ?string $context, ?string $locale = null): ?Group
    {
        $hash = md5((string) json_encode(['pattern' => $originalPattern, 'context' => $context]));
        $query = GroupModel::query()->where('hash', '=', $hash);

        if ($locale !== null) {
            $query->where('locale', '=', $locale);
        }

        $result = $query->first();

        return $result === null ? null : $this->makeGroup($result);
    }

    public function find(GroupId $id): ?Group
    {
        $result = GroupModel::query()->where('id', '=', (int) $id->value)->first();

        return $result === null ? null : $this->makeGroup($result);
    }

    public function list(int $offset, int $limit): array
    {
        return array_map([$this, 'makeGroup'], GroupModel::query()->limit($limit)->offset($offset)->get()->all());
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