<?php

declare(strict_types=1);

namespace EugeneErg\LaravelIcuI18nTranslate\Repositories\Read;

use EugeneErg\IcuI18nTranslator\Entities\Translate;
use EugeneErg\IcuI18nTranslator\Repositories\ReadTranslateRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\GroupId;
use EugeneErg\IcuI18nTranslator\ValueObjects\TranslateId;
use EugeneErg\LaravelIcuI18nTranslate\Models\TranslateModel;

final readonly class ReadTranslateRepository implements ReadTranslateRepositoryInterface
{
    public function find(string $pattern, string|null $locale = null): Translate|null
    {
        $hash = md5($pattern);
        $query = TranslateModel::query()->where('hash', '=', $hash);

        if ($locale !== null) {
            $query->where('locale', '=', $locale);
        }

        $result = $query->first();

        return $result === null ? null : $this->makeTranslate($result);
    }

    public function findByGroup(GroupId $groupId, string $key, string $locale): Translate|null
    {
        $result = TranslateModel::query()
            ->join('icu_i18n_group_translates as igt', 'igt.translate_id', '=', 'icu_translates.id')
            ->where('igt.group_id', '=', $groupId->value)
            ->where('icu_translates.locale', '=', $locale)
            ->where('igt.key', '=', $key)
            ->select('icu_translates.*')
            ->first();

        return $result === null ? null : $this->makeTranslate($result);
    }

    public function groupListByKey(GroupId $groupId, string $locale): array
    {
        /** @var array<string, TranslateModel> $result */
        $result = TranslateModel::query()
            ->join('icu_i18n_group_translates as igt', 'igt.translate_id', '=', 'icu_translates.id')
            ->where('igt.group_id', '=', $groupId->value)
            ->where('icu_translates.locale', '=', $locale)
            ->select('igt.key', 'icu_translates.*')
            ->get()
            ->keyBy('key')
            ->all();

        return array_map([$this, 'makeTranslate'], $result);
    }

    public function keysListByKey(GroupId $groupId, string $locale, array $keys): array
    {
        /** @var array<string, TranslateModel> $result */
        $result = TranslateModel::query()
            ->join('icu_i18n_group_translates as igt', 'igt.translate_id', '=', 'icu_translates.id')
            ->where('igt.group_id', '=', $groupId->value)
            ->where('icu_translates.locale', '=', $locale)
            ->whereIn('igt.key', $keys)
            ->select('igt.key', 'icu_translates.*')
            ->get()
            ->keyBy('key')
            ->all();

        return array_map([$this, 'makeTranslate'], $result);
    }

    private function makeTranslate(TranslateModel $model): Translate
    {
        return new Translate(
            id: new TranslateId((string) $model->id),
            pattern: $model->pattern,
            locale: $model->locale,
        );
    }
}
