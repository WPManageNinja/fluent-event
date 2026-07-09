<?php

namespace FluentEvents\Framework\Database\Orm;

interface Castable
{
    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array  $arguments
     * @return string
     * @return string|\FluentEvents\Framework\Database\Orm\CastsAttributes|\FluentEvents\Framework\Database\Orm\CastsInboundAttributes
     */
    public static function castUsing(array $arguments);
}
