<?php

namespace FluentEvents\Framework\Container\Contracts;

use Exception;
use FluentEvents\Framework\Container\Contracts\Psr\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
