<?php

namespace FluentEvent\Framework\Container\Contracts;

use Exception;
use FluentEvent\Framework\Container\Contracts\Psr\ContainerExceptionInterface;

class CircularDependencyException extends Exception implements ContainerExceptionInterface
{
    //
}
