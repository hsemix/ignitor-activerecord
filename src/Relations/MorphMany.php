<?php

namespace Igniter\ActiveRecord\Relations;

use Igniter\ActiveRecord\Model; 
use Igniter\ActiveRecord\Query\Builder;

class MorphMany extends HasMany
{
    public $mergeType;
    public $mergeClass;
    public $related;

    public function __construct(Builder $query, Model $parent, string $type, string $id, string $localKey)
    {
        $this->mergeType = $type;       
        $this->query = $query;
        $this->mergeClass = basename(str_replace('\\', '/', $parent->getMergeableClass()));//class_base($parent->getMergeableClass());
        $this->foreignKey = $id;
        $this->parent = $parent;
        
        parent::__construct($query, $parent, $id, $localKey);
    }

    public function addConditions()
    {     
        $this->query->where($this->mergeType, strtolower($this->mergeClass))->where($this->foreignKey, '=', $this->getParentIdValue());
    }

    public function save(Model $model)
    {
        $mergeClassBase = basename(str_replace('\\', '/', $this->mergeClass));
        $model->setAttribute($this->getPlainMergeableType(), strtolower($mergeClassBase));
        $model->setAttribute($this->getPlainMergeId(), $this->parent->{$this->parent->getPrimaryKey()});
        
        return parent::save($model);
    }

    public function firstOrCreate(array $attributes)
    {
        if (is_null($instance = $this->where($attributes)->first())) {
            $instance = $this->create($attributes);
        }

        return $instance;
    }

    public function create(array $attributes)
    {
        $instance = $this->related->newInstance($attributes);
        $this->setForeignAttributesForCreate($instance);
        $instance->save();
        return $instance;
    }

    protected function setForeignAttributesForCreate(Model $model)
    {
        $model->{$this->getPlainForeignKey()} = $this->getParentKey();
        $model->{$this->last(explode('.', $this->mergeType))} = $this->mergeClass;
    }

    public function getPlainMergeId()
    {
        return $this->last(explode('.', $this->foreignKey));
    }

    public function getPlainMergeableType()
    {
        return $this->last(explode('.', $this->mergeType));
    }

    public function last(array $colllection)
    {
        return end($colllection);
    }
    
    public function saveMany($models)
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $models;
    }
}
