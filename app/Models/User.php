<?php

namespace FluentEvents\App\Models;

use FluentEvents\App\Models\Model;
use FluentEvents\Framework\Database\Orm\UserProxyTrait;

class User extends Model
{
    use UserProxyTrait;

    public $timestamps = false;

    protected $table = 'users';

    protected $primaryKey = 'ID';
}
