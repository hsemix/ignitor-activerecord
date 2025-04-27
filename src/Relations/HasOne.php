<?php

namespace Igniter\ActiveRecord\Relations;

use Igniter\ActiveRecord\Model;
use Igniter\ActiveRecord\Query\Builder;

class HasOne extends Relation
{
    protected $child;
    protected $query;
    protected $parent;
    protected $otherKey;
    protected $foreignKey;

    public function __construct(Builder $query, Model $parent, $foreignKey, $otherKey)
    {
        $this->otherKey = $otherKey;
        $this->child = $query->getModel();
        $this->foreignKey = $foreignKey;
        $this->query = $query;
        $this->parent = $parent;
        parent::__construct($query, $parent);
    }

    public function addConditions()
    {
        $this->query->where($this->foreignKey, '=', $this->getParentIdValue())->limit(1);
    }

    public function getParentIdValue()
    {
        return $this->parent->getAttribute($this->otherKey);
    }

    public function save(Model $model)
    {
        $model->setAttribute($this->getPlainForeignKey(), $this->getParentIdValue());

        return $model->save() ? $model : false;
    }

    public function getPlainForeignKey()
    {
        $foreign = explode(".", $this->foreignKey);
        return end($foreign);
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
