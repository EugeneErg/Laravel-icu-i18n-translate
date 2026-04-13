<?php

declare(strict_types = 1);

namespace EugeneErg\TranslateLaravel\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $original_pattern
 * @property string $pattern
 * @property string $locale
 * @property string|null $context
 * @property string $hash
 */
final class GroupModel extends Model
{
    protected $table = 'icu_i18n_groups';
    protected $guarded = ['id'];

    protected $casts = [
        'id' => 'int',
        'original_pattern' => 'string',
        'pattern' => 'string',
        'locale' => 'string',
        'context' => 'string',
        'hash' => 'string',
    ];
}