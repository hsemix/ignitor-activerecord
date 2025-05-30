<?php

namespace Igniter\ActiveRecord\Relations;

use Closure;
use Igniter\ActiveRecord\Model;
use Igniter\ActiveRecord\Query\Builder;
use Igniter\ActiveRecord\Contracts\Relation as RelationContract;

abstract class Relation implements RelationContract
{
    protected $query;
    protected $parent;
    protected $child;
    static $conditions;

    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->child = $query->getModel();
        
        $this->addConditions();
    }

    abstract public function addConditions();

    /**
     * Redirect all unknown methods to the query Builder, it could be aware of them
     */
    public function __call($method, $parameters)
    {
        $result = call_user_func_array([$this->query, $method], $parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    public function noConditions(Closure $callback)
    {
        return call_user_func($callback);
    }

    public function getLazy()
    {
        return $this->get();
    }

    protected function getKeys(array $models, $key = null)
    {
        return array_unique(array_values(array_map(function ($value) use ($key) {
            return $key ? $value->getAttribute($key) : $value->getPrimaryKey();
        }, $models)));
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    abstract public function getResults();
}
