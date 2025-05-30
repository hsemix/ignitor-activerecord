<?php

namespace Igniter\ActiveRecord\Relations;

use Igniter\ActiveRecord\Model;
use Igniter\ActiveRecord\Collection;
use Igniter\ActiveRecord\Query\Builder;

class HasMany extends Relation
{
    protected $child;
    protected $query;
    protected $parent;
    protected $otherKey;
    protected $foreignKey;
    
    public function __construct(Builder $query, Model $parent, string $foreignKey, string $otherKey)
    {
        $this->otherKey = $otherKey;
        $this->child = $query->getModel();
        $this->foreignKey = $foreignKey;
        $this->query = $query;
        $this->parent = $parent;
        parent::__construct($query, $parent);
    }

    public function getChild()
    {
        return $this->child;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function addConditions()
    {
        $this->query->where($this->foreignKey, '=', $this->getParentIdValue());
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

    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }

    public function bootRelation(array $models, $relation)
    {
        
        foreach ($models as $model) {
            $model->setRelation($relation, $model->$relation);
        }

        return $models;
    }
    
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchMany($models, $results, $relation);
    }

    public function matchMany(array $models, Collection $results, $relation)
    {
        return $this->matchOneOrMany($models, $results, $relation, 'many');
    }

    protected function matchOneOrMany(array $models, Collection $results, $relation, $type)
    {
        return $models;
    }

    public function addLazyConditions(array $models)
    {
        $this->query->whereIn($this->getPlainForeignKey(), $this->getKeys($models, $this->otherKey));
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
