<?php

namespace FluentEvent\Framework\Database\Orm;

interface Castable
{
    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return string
     * @return string|\FluentEvent\Framework\Database\Orm\CastsAttributes|\FluentEvent\Framework\Database\Orm\CastsInboundAttributes
     */
    public static function castUsing(array $arguments);
}
