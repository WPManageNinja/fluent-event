<?php

namespace FluentEvent\Framework\Database\Orm;

interface Scope
{
    /**
     * Apply the scope to a given Orm query builder.
     *
     * @param  \FluentEvent\Framework\Database\Orm\Builder  $builder
     * @param  \FluentEvent\Framework\Database\Orm\Model  $model
     * @return void
     */
    public function apply(Builder $builder, Model $model);
}
