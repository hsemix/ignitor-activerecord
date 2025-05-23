<?php

namespace Igniter\ActiveRecord\Relations;

use Igniter\ActiveRecord\Model;
use Igniter\ActiveRecord\Query\Builder;

class BelongsToMany extends Relation
{
    protected $table;
    protected $otherKey;
    protected $foreignKey;
    protected $pivotCreatedAt;
    protected $pivotWheres = [];
    protected $pivotColumns = [];
    protected $pivotWhereIns = [];

    public function __construct(Builder $query, Model $parent, string $table, string $foreignKey, string $otherKey)
    {
        $this->table = $table;
        $this->otherKey = $otherKey;
        $this->foreignKey = $foreignKey;
        $this->parent = $parent;
        $this->query = $query;
        parent::__construct($query, $parent);
    }

    public function addConditions()
    {
        $this->setJoins();
        $this->setWhereClause();
    }

    protected function setWhereClause()
    {
        $foreign = $this->getForeignKey();
        $this->query->where($foreign, '=', $this->getParentIdValue());//->select($this->query->from.'.*');
        return $this;
    }

    public function getParentIdValue()
    {
        return $this->parent->getAttribute($this->parent->getPrimaryKey());
    }

    public function getForeignKey()
    {
        return $this->table.'.'.$this->foreignKey;
    }

    protected function setJoins($query = null)
    {
        $query = $query ? $query : $this->query;
        $baseTable = $this->query->getModel()->getTable();
        
        $key = $baseTable.'.'.$this->query->getModel()->getPrimaryKey();
        
        $query->join($this->table, $key, '=', $this->getOtherKey())->select($baseTable.'.*');
        
        return $this;
    }
    
    protected function getOtherKey()
    {
        return $this->table.'.'.$this->otherKey;
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->get();
    }
}
