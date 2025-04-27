<?php

namespace Igniter\ActiveRecord\Relations;

use Igniter\ActiveRecord\Model;
use Igniter\ActiveRecord\Query\Builder;

class BelongsTo extends Relation
{

    protected $child;
    protected $otherKey;
    protected $foreignKey;

    public function __construct(Builder $query, Model $parent, string $foreignKey, string $otherKey, Model $child)
    {
        $this->otherKey = $otherKey;
        $this->child = $child;
        $this->foreignKey = $foreignKey;
        $this->parent = $parent;
        $this->query = $query;
        parent::__construct($query, $parent);
    }

    public function addConditions()
    {
        $table = $this->child->getTable();
        $this->query->where($table.'.'.$this->otherKey, '=', $this->parent->{$this->foreignKey});
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first();
    }
}
