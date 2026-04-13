<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $group_id
 * @property int $translate_id
 * @property string $key
 * @property int|null $source_id
 */
final class GroupTranslateModel extends Model
{
    protected $table = 'icu_i18n_group_translates';
    protected $guarded = ['id'];

    protected $casts = [
        'group_id' => 'int',
        'translate_id' => 'int',
        'key' => 'string',
        'source_id' => 'int',
    ];
}