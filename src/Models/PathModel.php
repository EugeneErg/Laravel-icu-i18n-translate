<?php

declare(strict_types=1);

namespace EugeneErg\LaravelIcuI18nTranslate\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int|null $group_id
 * @property int $id
 * @property int|null $parent_id
 * @property string $value
 */
final class PathModel extends Model
{
    protected $table = 'icu_i18n_paths';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'int',
        'value' => 'string',
        'group_id' => 'int',
        'parent_id' => 'int',
    ];
}
