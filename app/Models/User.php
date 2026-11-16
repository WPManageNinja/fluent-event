<?php

namespace FluentEvent\App\Models;

use FluentEvent\App\Models\Model;
use FluentEvent\Framework\Database\Orm\UserProxyTrait;

class User extends Model
{
    use UserProxyTrait;

    public $timestamps = false;

    protected $table = 'users';

    protected $primaryKey = 'ID';

    protected $fillable = [];

    protected $guarded = ['*'];
}
