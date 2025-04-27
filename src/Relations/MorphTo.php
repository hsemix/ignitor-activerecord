<?php

namespace Igniter\ActiveRecord\Relations;

use Igniter\ActiveRecord\Model;
use Igniter\ActiveRecord\Query\Builder;

class MorphTo extends BelongsTo
{
    protected $models;
    protected string $mergeType;

    public function __construct(Builder $query, Model $parent, string $foreignKey, string $otherKey, string $type, Model $relation)
    {
        $this->mergeType = $type;
        parent::__construct($query, $parent, $foreignKey, $otherKey, $relation);
    }
}