<?php

declare(strict_types=1);

/**
 * This file is part of the Igniter framework.
 *
 * @package    Ignitor\ActiveRecord
 * @category   ActiveRecord
 * @author     Hamid Ssemitala <semix.hamidouh@gmail.com>
 * @link       https://github.com/ignitor/active-record
 * @license    https://opensource.org/licenses/MIT  MIT License
 * @link       https://igniter.com
 */

namespace Igniter\ActiveRecord;

use Closure;
use DateTime;
use Exception;
use ArrayAccess;
use LogicException;
use Config\Database;
use JsonSerializable;
use CodeIgniter\Database\BaseBuilder;
use Igniter\ActiveRecord\Support\Str;
use Igniter\ActiveRecord\Query\Builder;
use Igniter\ActiveRecord\Support\Inflect;
use Igniter\ActiveRecord\Relations\HasOne;
use Igniter\ActiveRecord\Relations\HasMany;
use Igniter\ActiveRecord\Relations\MorphTo;
use Igniter\ActiveRecord\Contracts\Relation;
use Igniter\ActiveRecord\Relations\MorphOne;
use Igniter\ActiveRecord\Relations\BelongsTo;
use Igniter\ActiveRecord\Relations\MorphMany;
use Igniter\ActiveRecord\Support\FileLocator;
use Igniter\ActiveRecord\Relations\BelongsToMany;

abstract class Model implements ArrayAccess, JsonSerializable
{
    protected ?string $table = null;
    protected string $primaryKey = 'id';
    protected bool $useTimestamps = false;
    protected array $allowedFields = [];
    protected bool $useSoftDeletes = false;
    private array $original = [];
    public array $relations = [];
    public array $attributes = [];
    public array $casts = [];
    public array $bootable = [];
    protected array $hidden = [];

    protected bool $protectFields = true;

    protected ?Builder $queryable = null;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected string $deleteKey = 'deleted_at';
    protected bool $exists = false;

    public function __construct(array $options = [])
    {
        $this->syncAttributes();
        $this->fillModelWith($options);
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return static
     */
    public function syncAttributes(): static
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Fill a model with attributes
     * 
     * @param array $attributes
     * 
     * @return static
     */
    public function fillModelWith(array $attributes): static
    {
        $this->fillModelWithSingle($attributes);
        return $this;
    }

    /**
     * Fill a model with a single array of attributes
     * 
     * @param array $attributes
     * 
     * @return static
     */
    protected function fillModelWithSingle(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (!empty($this->allowedFields)) {
                if (in_array($key, $this->allowedFields)) {
                    $this->setAttribute($key, $value);
                }
            } else {
                if (!$this->protectFields) {
                    $this->setAttribute($key, $value);
                } else {
                    throw new Exception('Need to have allowedFields for mass assignment or set protected bool $protectFields to false in your model (' . static::class . ')');
                }
            }
        }
        return $this;
    }

    /**
     * get a variable and make an object point to it
     * 
     * @return mixed
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Return all attributes
     * 
     * @return array
     */
    public function getRawAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set a variable and make an object point to it
     * 
     * @param string $key
     * @param mixed $value
     * 
     * @return void
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     *
     * @param  string  $key
     * 
     * @return bool
     */
    public function __isset(string $key)
    {
        return !is_null($this->getAttribute($key));
    }

    /**
	 * Make the object act like an array when at access time
	 *
     * @param mixed $offset
     * @param mixed $value
     * 
     * @return void
	 */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->attributes[] = $value;
        } else {
            $this->attributes[$offset] = $value;
        }
    }

    /**
     * Determine whether an attribute exists on this model
     * 
     * @param $offset
     * 
     * @return boolean
     */
    public function offsetExists($offset): bool 
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Unset an attribute if it doesn't exist
     * 
     * @param $offset
     * 
     * @return void
     */
    public function offsetUnset($offset): void 
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get the value of an attribute from an array given its key
     * 
     * @param string $offset
     * 
     * @return mixed
     */
    public function offsetGet($offset): mixed 
    {
        return isset($this->attributes[$offset]) ? $this->attributes[$offset] : null;
    }

    /**
     * Change an object to an array
     * 
     * @param null
     * 
     * @return mixed
     */
    public function toArray()
    {
        return $this->jsonSerialize();
    }

    /**
     * Implement a json serializer
     * 
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        $attributes = (array) $this->attributes;
        if (!empty($this->jsonInclude)) {
            foreach ($this->jsonInclude as $field) {
                $attributes[$field] = $this->getAttribute($field);
            }
        }

        if (!empty($this->relations)) {
            foreach ($this->relations as $field => $relations) {
                if (!is_null($relations)) {
                    $attributes[$field] = $relations->toArray();
                }
            }
        }
    
        $attributes = array_map(function($attribute) {
            if (!is_array($attribute)) {
                if (!is_object($attribute)) {
                    if (!empty($attribute)) {
                        $json_attribute = json_decode((string)$attribute, true);
                        if (json_last_error() == JSON_ERROR_NONE)
                            return $json_attribute;
                    }
                    return $attribute;
                } else {
                    return (array)$attribute;
                }
            }
            return $attribute;
        }, $attributes);

        return $this->removeHiddenFields($attributes);
    }

    /**
     * Remove given fields from the model attributes when casted to array or json
     * 
     * @param array|[] $attributes
     * 
     * @return array $items
     */
    protected function removeHiddenFields(array $attributes = [])
    {
        $attributeKeys = array_keys($attributes);
        $removedHiddenFields = array_diff($attributeKeys, $this->hidden);    
        $items = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $removedHiddenFields)) {
                $items[$key] = $value;
            }
        }
        return $items;
    }

    /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
     * 
     * @return void
     */
    public function __unset(string $key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    /**
     * Change the model to a json string
     * 
     * @param int $options
     * 
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }


    /**
     * Set a model attribute
     * 
     * @param string $key
     * @param mixed
     * 
     * @return static
     */
    public function setAttribute(string $key, mixed $value)
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * 
     * @return mixed
     */
    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        } elseif (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        } else {
            $camelizedKey = Str::camelize($key);

            if (method_exists($this, $camelizedKey)) {
                return $this->getRelationFromMethod($key, $camelizedKey);
            }
        }
    }

    protected function getRelationFromMethod($key, $camelizedKey)
    {
        $relation = call_user_func([$this, $camelizedKey]);

        if (!$relation instanceof Relation) {
            throw new LogicException('Relationship method must return an object of type [Igniter\ActiveRecord\Contracts\Relation]');
        }

        return $this->relations[$key] = $relation->getResults();
    }

    /**
     * Set Raw attributes of the model
     * 
     * @param array $attributes
     * @param boolean $sync
     * 
     * @return static
     */
    public function setRawAttributes(array $attributes, bool $sync = false)
    {
        $this->attributes = $attributes;
        if ($sync) {
            $this->syncAttributes();
        }
        return $this;
    }

    /**
     * Get the model table
     * 
     * @return string
     */
    public function getTable() //: string
    {
        $class = static::class;
        $calledClass = basename(str_replace('\\', '/', $class));

        if (!is_null($this->table)) {
            return $this->table;
        }

        $calledClass = Str::snakeCase($calledClass);
        return Inflect::pluralize(Str::snakeCase($calledClass));
    }

    /**
     * Set a table correponding to this model for database queries
     * 
     * @param string $table
     * 
     * @return static
     */
    public function setTable(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Get the primary key of the table corresponding to this Model
     * 
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Get the delete key of the table corresponding to this model
     * 
     * @param null
     * 
     * @return string
     */
    public function getDeleteKey()
    {
        return $this->deleteKey;
    }

    /**
     * Get a model QueryBuilder instance from the model
     * 
     * @return Builder
     */
    public static function query(?string $rawQuery = null, array $bindings = [])
    {
        $model = new static();
        return $model->newMainQueryBuilder($model, $rawQuery, $bindings);
    }

    /**
     * Get a model QueryBuilder instance from the model
     * 
     * @return BaseBuilder
     */
    public static function ciQuery()
    {
        $model = new static();
        return $model->newMainQueryBuilder($model)->getBuilder();
    }

    /**
     * Get a model QueryBuilder instance from the model
     * 
     * @return Builder
     */
    public function newQueryBuilder()
    {
        return $this->newMainQueryBuilder($this);
    }

    /**
     * Get a model QueryBuilder instance from the model
     * 
     * @return Builder
     */
    protected function newMainQueryBuilder(?Model $model = null, ?string $rawQuery = null, array $bindings = [])
    {
        $queryable = $this->queryable ?: new Builder(Database::connect(), $model);

        if ($rawQuery) {
            $queryable->setRawQuery($rawQuery, $bindings);
        }
        
        return $queryable;
    }

    /**
     * Make a new Instance of the Model class
     * 
     * @param array|[] $attributes
     * @param boolean $exists
     * 
     * @return static
     */
    public function newInstance(array $attributes = [], $exists = false)
    {
        $model = new static((array) $attributes);
        $model->exists = $exists;
        return $model;
    }

    /**
     * decides whether to update or create an object
     * 
     * @param array $options 
     * 
     * @return Model $saved
     */
    public function save(array $options = [])
    {
        $query = $this->newQueryBuilder();
        // Dispatch a "saving" event
        if ($this->exists) {
            $saved = $this->performUpdate($query, $options);
        } else {
            $saved = $this->performInsert($query, $options);
        }
        // Dispatch a "saved" event
        return $saved;
    }

    /**
     * create and save an object
     * 
     * @param Builder $query
     * @param array|[] $options
     * 
     * @return static
     */
    protected function performInsert(Builder $query, array $options = [])
    {
        // Dispatch a "creating" event
        if ($this->useTimestamps) {
            $this->setTimestamps();
            $this->createTimestamps();
        }

        $attributes = $this->attributes;

        if (count($options) !== 0) {
            /* Only save valid columns */
            $options = array_filter($options, function ($key) {
                return (!in_array($key, $this->attributes, true) === true);
            }, ARRAY_FILTER_USE_KEY);

            $attributes = array_merge($attributes, $options);
        }

        $query->create($attributes);
        $this->exists = true;
        $this->{$this->getPrimaryKey()} = $query->getModel()->{$this->getPrimaryKey()};
        // Dispatch a "created" event
        return $this;
    }

    /**
     * Get the updated_at field name of the model
     * 
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return static::UPDATED_AT;
    }

    /**
     * Get the created_at field name of the model
     * 
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return static::CREATED_AT;
    }

    /**
     * Created updated_at and created_at fields in the databae table corresponding to this model if they don't already exist
     * 
     * @return mixed
     */
    protected function createTimestamps()
    {
        // return $this->newQuery()->dates($this->getUpdatedAtColumn(), $this->getCreatedAtColumn());
    }

    /**
     * Check the model for dirty fields
     *
     * @param array|[] $options
     * 
     * @return array
     */
    protected function checkDirtyOptions(array $options = [])
    {
        if (count($options) !== 0) {
            /* Only save valid columns */
            $options = array_filter($options, function ($key) {
                return (!in_array($key, $this->attributes, true) === true);
            }, ARRAY_FILTER_USE_KEY);

            $this->attributes = array_merge($this->attributes, $options);
        }

        return $this->attributes;
    }

    /**
     * Update a record in the database table corresponding to this model
     * 
     * @param Builder $query
     * @param array|[] $options
     * 
     * @return boolean
     */
    protected function performUpdate(Builder $query, array $options = []): bool
    {
        if (count($options) !== 0) {
            $this->checkDirtyOptions($options);
        }

        if (!$this->getDirty()) {
            return false;
        }
        
        // Dispatch a "updating" event

        if ($this->useTimestamps) {
            $this->setTimestamps();
            $this->createTimestamps();
        }
        $this->setKeysForSaveQuery($query)->update($this->getDirty());
        // Dispatch a "updated" event
        return true;
    }

    /**
     * Set primary keys of the updated model to the current query
     * 
     * @param Builder $query
     * 
     * @return Builder $query
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->getPrimaryKey(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Get the primary key value of the Model that has just been saved
     * 
     * @param null
     * 
     * @return mixed
     */
    protected function getKeyForSaveQuery()
    {
        return $this->getAttribute($this->getPrimaryKey());
    }

    /**
     * Set timestamps to new values
     * 
     * @param null
     * 
     * @return void
     */
    protected function setTimestamps()
    {
        $time = $this->newTimestamp();

        if (!$this->isDirty(static::UPDATED_AT)) {
            $this->setUpdatedAt($time);
        }

        if (!$this->exists && !$this->isDirty(static::CREATED_AT)) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Get an time stamp string
     * 
     * @param null
     * 
     * @return string
     */
    protected function newTimestamp()
    {
        $now = new DateTime();
        return $now->format('Y-m-d H:i:s');
    }

    /**
     * Set a created_at value
     * 
     * @param string $value
     * 
     * @return static
     */
    public function setCreatedAt($value)
    {
        $this->{static::CREATED_AT} = $value;
        return $this;
    }

    /**
     * Set an updated_at value
     * 
     * @param string $value
     * 
     * @return static
     */
    public function setUpdatedAt($value)
    {
        $this->{static::UPDATED_AT} = $value;
        return $this;
    }

    /**
     * Update the timestamps fields of the Model database table
     * 
     * @param null
     * 
     * @return static
     */
    public function updateTimestamps()
    {
        if (!$this->useTimestamps) {
            return false;
        }
        $this->setTimestamps();

        return $this->save();
    }

    /**
     * Determine whether the fields have been edited
     * 
     * @param array|null $attributes
     * 
     * @return boolean
     */
    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();
		
        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        if (! is_array($attributes)) {
            $attributes = func_get_args();
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @param null
     * 
     * @return array $dirty
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->bootable)) {
                if (!array_key_exists($key, $this->original)) {
                    $dirty[$key] = $value;
                } elseif ($value !== $this->original[$key] && !$this->originalIsNumericallyEquivalent($key)) {
                    $dirty[$key] = $value;
                }
            }
        }

        return $dirty;
    }

    /**
     * Determine if the new and old values for a given key are numerically equivalent.
     *
     * @param string $key
     * 
     * @return bool
     */
    protected function originalIsNumericallyEquivalent(string $key)
    {
        $current = $this->attributes[$key];

        $original = $this->original[$key];

        return is_numeric($current) && is_numeric($original) && strcmp((string) $current, (string) $original) === 0;
    }

    /**
     * Create a new Elegant model from query
     * 
     * @param array|[] $attributes
     * @param array|null $bootable
     * 
     * @return Model $model
     */
    public function newFromQuery($attributes = [], array $bootable = [], array $items = [])
    {
        $model = $this->newInstance([], true);
        $model->setRawAttributes((array) $attributes, true);
        $model->attributes = (array)$attributes;

        if (!is_null($bootable)) {
            $this->bootable = $bootable;
            foreach ($bootable as $start => $class) {
                if ($start != 'pagination' && $start != 'paginator')
                    $this->invokeBootable($start, $model, $items);
                else 
                    $model->$start = $class;
            }
        }

        return $model;
    }

    /**
     * Relationships
     */
    /**
     * Invoke funtions or return strings or arrays that functions return
     * 
     * @param string $name
     * @param Model $model
     * @param array $items
     * 
     * @return void
     */
    protected function invokeBootable($name, $model, array $items)
    {
        $with = $this->bootable[$name];

        // echo '<pre>';
        // print_r($with);
        // die();

        if (is_numeric($name) === true) {
            $name = $with;
        }

        if ($with instanceof Closure) {
            $result = $this->processBootableClosure($with, $model, $name, $items);
        } else {
            $result = $this->processBootableMethod($with, $model, $name, $items);
        }

        if (is_array($result)) {
            if (array_key_exists('field', $result) && array_key_exists('results', $result)) {
                $name = $result['field'];
                $result = $result['results'];
            }
        }
    
        if ($result instanceof Collection) {
            $model->relations[$name] = $result;
        } elseif($result instanceof Model) {
            $model->relations[$name] = $result;
        } else {
            $model->{$name} = $result;
            $model->bootable[$name] = $result;
        }
    }

     /**
     * Process methods included in the bootable array
     * 
     * @param string $with
     * @param Model $model
     * @param string $name
     * 
     * @return mixed $result
     */
    protected function processBootableMethod($with, $model, $name, $items)
    {
        if (!is_array($with) && !is_object($with)) {
            if (!$this->isNested($name, explode('.', $with)[0])) {
                if (method_exists($model, $name)) {

                    /** @var Relation $result */
                    $result = $model->$name();

                    if ($result instanceof Relation) {
                        if ($result instanceof HasOne || $result instanceof BelongsTo || $result instanceof MorphOne) {
                            $result = $result->first();
                        } else {
                            $result = $result->get();
                        }
                        
                    }
                } else {
                    $result = $with;
                }
            } else {
                $result = $this->processNestedWith($with, $model, $name, $items);
            }
        } else {
            $result = $with;
        }
        
        return $result;
    }

    /**
     * Process closures included in the bootable array
     * 
     * @param string $with
     * @param Model $model
     * @param string $name
     * 
     * @return mixed
     */
    protected function processBootableClosure($with, $model, $name)
    {
        $result = call_user_func($with, $model);

        if (is_null($result)) {
            $result = $model->$name;
        } else if (is_object($result)) {
            $result = $result->get();
        }

        return $result;
    }

    /**
     * Process nested relations passed in the with method
     * 
     * @param string $with
     * @param Model $model
     * @param string $name
     * 
     * @return mixed
     */
    protected function processNestedWith($with, $model, $name, $items)
    {
        $names = explode('.', $name);
        if (method_exists($model, $names[0])) {
            $method = $names[0];
            /** @var Relation $result */
            $result = $model->$method();

            if ($result instanceof Relation) {
                unset($names[0]);
                $result = $result->with(implode('.', $names));//->get();

                if ($result instanceof HasOne || $result instanceof BelongsTo || $result instanceof MorphOne) {
                    $result = $result->first();
                } else {
                    $result = $result->get();
                }

            } else {
                if (in_array($method, $this->virtualRelations)) {
                    if ($result instanceof Model) {
                        unset($names[0]);
                        $result = $result->with(implode('.', $names))->get();
                    } else if ($result instanceof Collection) {
                        unset($names[0]);
                        $result = isset($result[0]) ? $result[0]->with(implode('.', $names))->get() : $result;
                    }
                }
            }
            $result =  ['field' => $method, 'results' => $result];
        } else {
            $result = null;
        }

        return $result;
    }

    /**
     * Determine whether a relation is nested
     * 
     * @param string $relation
     * 
     * @return boolean
     */
    protected function isNested($name, $relation)
    {
        $dots = str_contains($name, '.');

        return $dots && str_starts_with($name, $relation.'.');
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param  string $class
     * @param  string|null $foreignKey
     * @param  string|null $otherKey
     * 
     * @return HasMany
     */
    public function hasMany(string $class, ?string $foreignKey = null, ?string $otherKey = null)
    {
		$foreignKey = $foreignKey?$foreignKey:$this->getForeignKey();
		$otherKey = $otherKey?$otherKey:$this->getPrimaryKey();
        
		$instance = new $class;
		return new HasMany($instance->newQueryBuilder(), $this, $instance->getTable().'.'.$foreignKey, $otherKey);
    }

    /**
     * Define a one-to-one relationship.
     *
     * @param string $class
     * @param string|null $foreignKey
     * @param string|null $otherKey
     * 
     * @return HasOne
     */
    public function hasOne(string $class, ?string $foreignKey = null, ?string $otherKey = null)
    {
		$foreignKey = $foreignKey?$foreignKey:$this->getForeignKey();
		$otherKey = $otherKey?$otherKey:$this->getPrimaryKey();

		$instance = new $class;
		return new HasOne($instance->newQueryBuilder(), $this, $instance->getTable().'.'.$foreignKey, $otherKey);
    }

    /**
	 * make the joins of sql queries one to one
	 *
     * @param string $class
     * @param string|null $foreignKey
     * @param string|null $otherKey
     * @param string|null $relation
     * 
     * @return BelongsTo
	 */
    public function belongsTo(string $class, ?string $foreignKey = null, ?string $otherKey = null, ?string $relation = null)
    {
        if (is_null($relation)) {
            list($current, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        if (is_null($foreignKey)) {
            $foreignKey = $relation . '_id';
        }
        
        $class = new $class;

        $query = $class->newQueryBuilder();
        $otherKey = $otherKey?$otherKey:$class->getPrimaryKey();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $class);
    }

        /**
     * Define a many-to-many relationship.
     *
     * @param string $class
     * @param string|null $table_name
     * @param string|null $first_table_primary_key
     * @param string|null $second_table_primary_key
     * @param string|null $parentKey
     * @param string|null $relatedKey
     * @param string|null $relation
     * 
     * @return BelongsToMany
     */
    public function belongsToMany(string $class, ?string $table_name = null, ?string $first_table_primary_key = null, ?string $second_table_primary_key = null, ?string $parent_key = null, ?string $related_key = null, ?string $relation = null)
    {
		$first_table_primary_key = $first_table_primary_key ?: $this->getForeignKey();
        
        $instance = new $class;

        $second_table_primary_key = ($second_table_primary_key)?$second_table_primary_key : $instance->getForeignKey();
		
        if (is_null($table_name)) {
            $table_name = $this->joinTables($class);
        }
		
        $query = $instance->newQueryBuilder();
		
        return new BelongsToMany($query, $this, $table_name, $first_table_primary_key, $second_table_primary_key);
    }

    /**
     * Join tables from classes
     * 
     * @param string $class
     * 
     * @return string
     */
    public function joinTables($class) 
    {
        $calledClass = basename(str_replace('\\', '/', $class));

        $thisClass = basename(str_replace('\\', '/', get_class($this)));

        $base = strtolower($thisClass);

        $class = strtolower($calledClass);

        $models = [$class, $base];
        sort($models);
        return strtolower(implode('_', $models));
    }

    /**
	 * make the joins of sql queries one to many of diffent types of objects in one type
     * 
     * @param string $class
     * @param string $mergable_name
     * @param string|null $mergeable_type
     * @param string|null $mergeable_id
     * @param string|null $primaryKey
	 *
     * @return MorphOne
	 */
    public function morphOne(string $class, string $mergeable_name, ?string $mergeable_type = null, ?string $mergeable_id = null, ?string $primaryKey = null)
    {
		$class = $this->returnAppropriateNamespace($class);

		$instance = new $class;

        list($mergeable_type, $mergeable_id) = $this->getMergeStrings($mergeable_name, $mergeable_type, $mergeable_id);
		$table = $instance->getTable();

        $primaryKey = $primaryKey?$primaryKey:$this->getPrimaryKey();

        return new MorphOne($instance->newQueryBuilder(), $this, $table.'.'.$mergeable_type, $table.'.'.$mergeable_id, $primaryKey);
		
    }

    /**
	 * make the joins of sql queries one to many of diffent types of objects in one type
     * 
     * @param string $class
     * @param string $mergable_name
     * @param string|null $mergeable_type
     * @param string|null $mergeable_id
     * @param string|null $primaryKey
	 *
     * @return MorphMany
	 */
    public function morphMany(string $class, string $mergeable_name, ?string $mergeable_type = null, ?string $mergeable_id = null, ?string $primaryKey = null)
    {
		$class = $this->returnAppropriateNamespace($class);

		$instance = new $class;

        list($mergeable_type, $mergeable_id) = $this->getMergeStrings($mergeable_name, $mergeable_type, $mergeable_id);
		$table = $instance->getTable();

        $primaryKey = $primaryKey?$primaryKey:$this->getPrimaryKey();

        return new MorphMany($instance->newQueryBuilder(), $this, $table.'.'.$mergeable_type, $table.'.'.$mergeable_id, $primaryKey);
		
    }
    
    /**
     * Get merged strings
     * 
     * @param string $name
     * @param string|null $type
     * @param string|null $id
     * 
     * @return array
     */
    protected function getMergeStrings(string $name, ?string $type = null, ?string $id = null)
    {
		if (!$type) {
			$type = $name."_type";
		}
		if (!$id) {
			$id = $name."_id";
		}
		return [$type, $id];
    }
    
    /**
     * Get a mergeable class
     * 
     * @param null
     * 
     * @return string
     */
    public function getMergeableClass(): string
    {
		return static::class;
    }
    
    /**
	 * returns one object from the caller class
	 *
     * @param string|null $mergeable_name
     * @param string|null $mergeable_type
     * @param string|null $mergeable_id
     * 
     * @return Mergeable
	 */
    public function morphTo(?string $mergeable_name = null, ?string $mergeable_type = null, ?string $mergeable_id = null)
    {
		$instance = new static;
		$debug = debug_backtrace();
		
		$string_for_merging = $debug[1]['function']; 

        if (!$mergeable_name) {
            $mergeable_name = $string_for_merging;
        }

		[$mergeable_type, $mergeable_id] = $this->getMergeStrings($mergeable_name, $mergeable_type, $mergeable_id);
		
        $class = $this->{$mergeable_type};
        if (!class_exists($class)) {
            $class = ucfirst(Str::camelize($this->{$mergeable_type}));
		    $class = $this->returnAppropriateNamespace($class);
        }
        
		$instance = new $class;	

        return new MorphTo($instance->newQueryBuilder(), $this, $mergeable_id, $instance->getPrimaryKey(), $mergeable_type, $instance);
    }

    /**
     * return the appropriate namespace of the model
     *
     * @param null
     * 
     * @return mixed
     */
    protected function buildNamespace(): mixed
    {
        return $this->getFinder()->getNamespaceFromClass(static::class);
    }

    /**
     * Return a new instance of the File locator
     * 
     * @param null
     * 
     * @return FileLocator
     */
    protected function getFinder()
    {
        return new FileLocator();
    }

    /**
     * Return an appropriate namespace of a relation
     * 
     * @param string $related
     * 
     * @return string $related
     */
    protected function returnAppropriateNamespace($related)
    {
        $classes = explode("\\", static::class);
        
        if (count($classes) > 1) {
            $namespaces = $this->getFinder()->getClassesOfNamespace($this->buildNamespace());
            foreach ($namespaces as $namespace) {
                if (strstr($namespace,  $related)) {
                    $related = $this->getFromDeclaredNamespaces($namespace, $related);
                } else {
                    $related = $this->buildRelatedNamespace($related);
                }
            }
        }
        return $related;
    }

    /**
     * Make a namespace of a relation
     * 
     * @param string $related
     * 
     * @return string $related
     */
    protected function buildRelatedNamespace($related)
    {
        $relatedNamespaces = explode("\\", $related);
        if (count($relatedNamespaces) == 1) {
            $related = $this->buildNamespace().'\\'.$related;
        }
        return $related;
    }

    /**
     * Change the model to a string
     * 
     * @param null
     * 
     * @return void
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * Build a find by any field in the database
     *
     * @param string $method
     * @param array $parameters
     * 
     * @return Builder|null
     * @throws Exception
     */
    public function __call($method, $parameters)
    {
        $query = $this->newQueryBuilder();
        if (preg_match('/^findBy(.+)$/', $method, $matches)) {
            return $this->where(Str::snakeCase($matches[1]), $parameters[0]);
        }

        if (method_exists($this, $span = 'span' . ucfirst($method)))
            return $query->callSpan($span, $parameters);
        if (method_exists($query, $method))
            return call_user_func_array([$query, $method], $parameters);
        return null;
    }

    /**
	 * Query the model statically and return a query builder
	 *
     * @param string $method
     * @param array $parameters
     * 
     * @return Builder
	 */
    public static function __callStatic($method, $parameters) 
    {
        $instance = new static;
        $query = $instance->newQueryBuilder();
        if (preg_match('/^findBy(.+)$/', $method, $matches)) {
            return $instance->where(Str::snakeCase($matches[1]), $parameters[0]);
        }

        if (method_exists($instance, $span = 'span' . ucfirst($method)))
            return $query->callSpan($span, $parameters);
        if (method_exists($query, $method))
            return call_user_func_array([$query, $method], $parameters);
        return null;
    }

    /**
     * Customize the clone behaviour of the model
     * 
     * @return void
     */
    public function __clone()
    {
        $this->queryable = clone $this->newQueryBuilder();

        $this->queryable->setModel($this);
    }
}
