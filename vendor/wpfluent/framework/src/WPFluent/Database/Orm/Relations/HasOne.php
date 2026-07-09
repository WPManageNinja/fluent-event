<?php

namespace FluentEvents\Framework\Database\Orm\Relations;

use FluentEvents\Framework\Database\Orm\Model;
use FluentEvents\Framework\Database\Orm\Builder;
use FluentEvents\Framework\Database\Orm\Collection;
use FluentEvents\Framework\Database\Query\JoinClause;
use FluentEvents\Framework\Database\Orm\Relations\Concerns\CanBeOneOfMany;
use FluentEvents\Framework\Database\Orm\SupportsPartialRelations;
use FluentEvents\Framework\Database\Orm\Relations\Concerns\ComparesRelatedModels;
use FluentEvents\Framework\Database\Orm\Relations\Concerns\SupportsDefaultModels;

class HasOne extends HasOneOrMany implements SupportsPartialRelations
{
    use ComparesRelatedModels, CanBeOneOfMany, SupportsDefaultModels;

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        if (is_null($this->getParentKey())) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array  $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  \FluentEvents\Framework\Database\Orm\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Add the constraints for an internal relationship existence query.
     *
     * Essentially, these queries compare on column names like "whereColumn".
     *
     * @param  \FluentEvents\Framework\Database\Orm\Builder  $query
     * @param  \FluentEvents\Framework\Database\Orm\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \FluentEvents\Framework\Database\Orm\Builder
     */
    public function getRelationExistenceQuery(
        Builder $query,
        Builder $parentQuery,
        $columns = ['*']
    ) {
        if ($this->isOneOfMany()) {
            $this->mergeOneOfManyJoinsTo($query);
        }

        return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
    }

    /**
     * Add constraints for inner join subselect for one of many relationships.
     *
     * @param  \FluentEvents\Framework\Database\Orm\Builder  $query
     * @param  string|null  $column
     * @param  string|null  $aggregate
     * @return void
     */
    public function addOneOfManySubQueryConstraints(
        Builder $query, $column = null, $aggregate = null
    ) {
        $query->addSelect($this->foreignKey);
    }

    /**
     * Get the columns that should be selected by the one of many subquery.
     *
     * @return array|string
     */
    public function getOneOfManySubQuerySelectColumns()
    {
        return $this->foreignKey;
    }

    /**
     * Add join query constraints for one of many relationships.
     *
     * @param  \FluentEvents\Framework\Database\Query\JoinClause  $join
     * @return void
     */
    public function addOneOfManyJoinSubQueryConstraints(JoinClause $join)
    {
        $join->on(
            $this->qualifySubSelectColumn($this->foreignKey),
            '=',
            $this->qualifyRelatedColumn($this->foreignKey)
        );
    }

    /**
     * Make a new related instance for the given model.
     *
     * @param  \FluentEvents\Framework\Database\Orm\Model  $parent
     * @return \FluentEvents\Framework\Database\Orm\Model
     */
    public function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance()->setAttribute(
            $this->getForeignKeyName(), $parent->{$this->localKey}
        );
    }

    /**
     * Get the value of the model's foreign key.
     *
     * @param  \FluentEvents\Framework\Database\Orm\Model  $model
     * @return mixed
     */
    protected function getRelatedKeyFrom(Model $model)
    {
        return $model->getAttribute($this->getForeignKeyName());
    }
}
