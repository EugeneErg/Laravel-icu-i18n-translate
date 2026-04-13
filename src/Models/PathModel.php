<?php

declare(strict_types = 1);

namespace EugeneErg\LaravelIcuI18nTranslate\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $value
 * @property int|null $group_id
 * @property int|null $parent_id
 */
final class PathModel extends Model
{
    protected $table = 'icu_i18n_paths';
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'int',
        'value' => 'string',
        'group_id' => 'int',
        'parent_id' => 'int',
    ];
}