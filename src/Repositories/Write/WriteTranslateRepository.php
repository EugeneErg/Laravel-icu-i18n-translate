<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Repositories\Write;

use EugeneErg\IcuI18nTranslator\Entities\Translate;
use EugeneErg\IcuI18nTranslator\Repositories\WriteTranslateRepositoryInterface;
use EugeneErg\IcuI18nTranslator\ValueObjects\TranslateId;
use EugeneErg\LaravelIcuI18nTranslate\Models\TranslateModel;

final readonly class WriteTranslateRepository implements WriteTranslateRepositoryInterface
{
    public function create(string $pattern, string $locale): Translate
    {
        $result = TranslateModel::query()->create([
            'locale' => $locale,
            'pattern' => $pattern,
            'hash' => md5($pattern),
        ]);

        return $this->makeTranslate($result);
    }

    public function delete(TranslateId $translateId): void
    {
        TranslateModel::query()->where('id', '=', $translateId->value)->delete();
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