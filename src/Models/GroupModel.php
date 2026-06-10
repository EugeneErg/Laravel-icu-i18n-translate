<?php

declare(strict_types=1);

namespace EugeneErg\LaravelIcuI18nTranslate\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string|null $context
 * @property string $hash
 * @property int $id
 * @property string $locale
 * @property string $original_pattern
 * @property string $pattern
 */
final class GroupModel extends Model
{
    public $timestamps = false;

    protected $table = 'icu_i18n_groups';

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'int',
        'original_pattern' => 'string',
        'pattern' => 'string',
        'locale' => 'string',
        'context' => 'string',
        'hash' => 'string',
    ];
}
