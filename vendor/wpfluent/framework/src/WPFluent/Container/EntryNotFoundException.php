<?php

namespace FluentEvent\Framework\Container;

use Exception;
use FluentEvent\Framework\Container\Contracts\Psr\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
