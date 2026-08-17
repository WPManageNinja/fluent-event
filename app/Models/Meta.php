<?php

namespace FluentEvent\App\Models;

/**
 * Meta model.
 *
 * Table: `wp_fe_meta` — see database/Migrations/MetaMigrator.php.
 */
class Meta extends Model
{
    protected $table = 'fe_meta';

    const OBJECT_TYPE_OPTION = 'option';
    const OBJECT_TYPE_USER = 'user';

    protected $fillable = [
        'object_type',
        'object_id',
        'meta_key',
        'value',
    ];

    protected $casts = [
        'value'     => 'array',
        'object_id' => 'int',
    ];

    public function scopeOfUser($query, $userId)
    {
        return $query->where('object_type', self::OBJECT_TYPE_USER)
            ->where('object_id', $userId);
    }

    public function scopeByKey($query, $metaKey)
    {
        return $query->where('meta_key', $metaKey);
    }
}
