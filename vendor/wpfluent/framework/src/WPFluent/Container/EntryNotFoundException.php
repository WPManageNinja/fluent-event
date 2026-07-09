<?php

namespace FluentEvents\Framework\Container;

use Exception;
use FluentEvents\Framework\Container\Contracts\Psr\NotFoundExceptionInterface;

class EntryNotFoundException extends Exception implements NotFoundExceptionInterface
{
    //
}
