<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $pattern
 * @property string $locale
 */
final class TranslateModel extends Model
{
    protected $table = 'icu_i18n_translates';
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'int',
        'pattern' => 'string',
        'locale' => 'string',
    ];
}